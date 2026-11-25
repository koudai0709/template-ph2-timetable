<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class CourseMaterialTest extends TestCase
{
    use DatabaseTransactions;

    private FilesystemAdapter $disk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('timetable_test', DB::connection()->getDatabaseName());
        $this->disk = Storage::fake('local');
    }

    public function test_guest_cannot_upload_or_download_material(): void
    {
        $course = $this->createCourse();
        $this->registerMaterial($course);

        $this->post(route('courses.material.store', $course), ['material' => $this->pdf()])
            ->assertRedirect('/login');
        $this->get(route('courses.material.download', $course))->assertRedirect('/login');
        $this->assertOldMaterialPreserved($course);
    }

    public function test_admin_can_upload_pdf_material(): void
    {
        $course = $this->createCourse();
        $file = $this->pdf('lecture.pdf', 5 * 1024);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post(route('courses.material.store', $course), ['material' => $file])
            ->assertRedirect("/courses/{$course->id}")
            ->assertSessionHasNoErrors();

        $course->refresh();
        $this->assertNotEmpty($course->material_path);
        $this->assertSame('lecture.pdf', $course->material_name);
        $this->disk->assertExists($course->material_path);
    }

    public function test_non_admin_cannot_upload_material(): void
    {
        $course = $this->createCourse();
        $student = $this->createStudent($course, 'approved');

        $this->actingAs($student)
            ->post(route('courses.material.store', $course), ['material' => $this->pdf()])
            ->assertForbidden();

        $this->assertDatabaseHas('courses', [
            'id' => $course->id, 'material_path' => null, 'material_name' => null,
        ]);
        $this->assertSame([], $this->disk->allFiles());
    }

    public function test_non_pdf_files_cannot_be_uploaded(): void
    {
        $course = $this->createCourse();
        $file = UploadedFile::fake()->create('text.pdf', 1, 'text/plain');

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->from("/courses/{$course->id}")
            ->post(route('courses.material.store', $course), ['material' => $file])
            ->assertRedirect("/courses/{$course->id}")
            ->assertSessionHasErrors('material');

        $this->assertNull($course->refresh()->material_path);
        $this->assertSame([], $this->disk->allFiles());
    }

    public function test_files_larger_than_five_mb_cannot_be_uploaded(): void
    {
        $course = $this->createCourse();

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->from("/courses/{$course->id}")
            ->post(route('courses.material.store', $course), ['material' => $this->pdf(size: 5121)])
            ->assertRedirect("/courses/{$course->id}")
            ->assertSessionHasErrors('material');

        $this->assertNull($course->refresh()->material_path);
        $this->assertSame([], $this->disk->allFiles());
    }

    public function test_approved_students_can_download_material(): void
    {
        $course = $this->createCourse();
        $this->registerMaterial($course);

        $this->actingAs($this->createStudent($course, 'approved'))
            ->get(route('courses.material.download', $course))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertDownload('old.pdf')
            ->assertStreamedContent("%PDF-1.4\nold material");
    }

    public function test_pending_rejected_and_unenrolled_students_cannot_download_material(): void
    {
        $course = $this->createCourse();
        $otherCourse = $this->createCourse();
        $this->registerMaterial($course);

        $students = [
            $this->createStudent($course, 'pending'),
            $this->createStudent($course, 'rejected'),
            User::factory()->create(['is_admin' => false]),
            $this->createStudent($otherCourse, 'approved'),
        ];

        foreach ($students as $student) {
            $this->actingAs($student)
                ->get(route('courses.material.download', $course))
                ->assertForbidden();
        }
    }

    public function test_admin_can_download_material(): void
    {
        $course = $this->createCourse();
        $this->registerMaterial($course);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('courses.material.download', $course))
            ->assertOk()
            ->assertDownload('old.pdf');
    }

    public function test_uploading_new_material_replaces_existing_material(): void
    {
        $course = $this->createCourse();
        $this->registerMaterial($course);
        $oldPath = $course->material_path;

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post(route('courses.material.store', $course), ['material' => $this->pdf('new.pdf')])
            ->assertRedirect("/courses/{$course->id}")
            ->assertSessionHasNoErrors();

        $course->refresh();
        $this->assertNotEmpty($course->material_path);
        $this->assertNotSame($oldPath, $course->material_path);
        $this->assertSame('new.pdf', $course->material_name);
        $this->disk->assertExists($course->material_path);
        $this->disk->assertMissing($oldPath);
    }

    public function test_old_material_is_preserved_when_replacement_fails(): void
    {
        $course = $this->createCourse();
        $this->registerMaterial($course);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $url = route('courses.material.store', $course);

        // 新しいファイルの書き込みが失敗したことにする。
        $failingDisk = Mockery::mock(FilesystemAdapter::class);
        $failingDisk->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::set('local', $failingDisk);
        try {
            $this->from("/courses/{$course->id}")
                ->post($url, ['material' => $this->pdf('new.pdf')])
                ->assertRedirect("/courses/{$course->id}")
                ->assertSessionHasErrors('material');
        } finally {
            Storage::set('local', $this->disk);
        }
        $this->assertOldMaterialPreserved($course);

        // SQLの更新後、トランザクションが確定する前に失敗させる。
        Course::updated(function (Course $updated) use ($course): void {
            if ($updated->is($course) && $updated->wasChanged('material_path')) {
                throw new RuntimeException('テスト用のDB更新失敗');
            }
        });

        $this->post($url, ['material' => $this->pdf('new.pdf')])->assertStatus(500);
        $this->assertOldMaterialPreserved($course);
    }

    public function test_material_cannot_be_downloaded_when_file_does_not_exist(): void
    {
        $course = $this->createCourse();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $url = route('courses.material.download', $course);

        $this->get($url)->assertNotFound();
        $course->forceFill([
            'material_path' => 'course-materials/missing.pdf',
            'material_name' => 'missing.pdf',
        ])->save();
        $this->get($url)->assertNotFound();
    }

    public function test_course_detail_page_is_displayed_successfully(): void
    {
        $course = $this->createCourse();
        $admin = User::factory()->create(['is_admin' => true]);
        $approved = $this->createStudent($course, 'approved');
        $pending = $this->createStudent($course, 'pending');
        $url = "/courses/{$course->id}";
        $uploadAction = 'action="' . route('courses.material.store', $course) . '"';
        $downloadLink = 'href="' . route('courses.material.download', $course) . '"';

        $this->actingAs($admin)->get($url)->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee($uploadAction, false)
            ->assertSeeText('アップロードする')
            ->assertSeeText('PDF教材は未登録です。')
            ->assertDontSee($downloadLink, false);
        $this->actingAs($approved)->get($url)->assertOk()
            ->assertSeeText('PDF教材は未登録です。')
            ->assertDontSee($downloadLink, false);

        $this->registerMaterial($course);

        $this->actingAs($admin)->get($url)->assertOk()
            ->assertSeeText('差し替える')
            ->assertSee($uploadAction, false)
            ->assertSee($downloadLink, false);
        $this->actingAs($approved)->get($url)->assertOk()
            ->assertSee($downloadLink, false)
            ->assertDontSee($uploadAction, false);
        $this->actingAs($pending)->get($url)->assertOk()
            ->assertDontSee($downloadLink, false)
            ->assertDontSee($uploadAction, false);
    }

    private function createCourse(): Course
    {
        $course = new Course();
        $course->forceFill([
            'code' => (string) Str::uuid(),
            'name' => '教材配布テスト用授業',
            'teacher' => 'テスト担当者',
            'day' => 1,
            'period' => 2,
            'room' => 'J201',
            'credits' => 2,
            'required' => false,
            'capacity' => 40,
        ])->save();

        return $course;
    }

    private function createStudent(Course $course, string $status): User
    {
        $student = User::factory()->create(['is_admin' => false]);
        $entry = $student->entries()->make(['course_id' => $course->id]);
        $entry->status = $status;
        $entry->decided_at = $status === 'pending' ? null : now();
        $entry->save();

        return $student;
    }

    private function pdf(string $name = 'lecture.pdf', int $size = 1): UploadedFile
    {
        return UploadedFile::fake()->create($name, $size, 'application/pdf');
    }

    private function registerMaterial(Course $course): void
    {
        $this->assertTrue($this->disk->put('course-materials/old.pdf', "%PDF-1.4\nold material"));
        $course->forceFill([
            'material_path' => 'course-materials/old.pdf',
            'material_name' => 'old.pdf',
        ])->save();
    }

    private function assertOldMaterialPreserved(Course $course): void
    {
        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'material_path' => 'course-materials/old.pdf',
            'material_name' => 'old.pdf',
        ]);
        $this->assertSame(['course-materials/old.pdf'], $this->disk->allFiles());
        $this->assertSame("%PDF-1.4\nold material", $this->disk->get('course-materials/old.pdf'));
    }
}
