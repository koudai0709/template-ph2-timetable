<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CourseRosterDownloadTest extends TestCase
{
    use DatabaseTransactions;

    private function createCourse(): Course
    {
        $id = DB::table('courses')->insertGetId([
            'code' => 'CSV-TEST',
            'name' => 'CSVテスト授業',
            'teacher' => 'テスト講師',
            'room' => '101',
            'day' => 1,
            'period' => 1,
            'credits' => 2,
            'required' => false,
            'capacity' => 40,
        ]);

        return Course::findOrFail($id);
    }

    private function createEntry(
        Course $course,
        User $user,
        string $status,
        ?string $grade = null
    ): void {
        DB::table('timetable_entries')->insert([
            'course_id' => $course->id,
            'user_id' => $user->id,
            'status' => $status,
            'grade' => $grade,
        ]);
    }

    public function test_guest_cannot_download(): void
    {
        $course = $this->createCourse();

        $this->get(
            route('courses.roster.download', $course)
        )->assertRedirect(route('login'));
    }

    public function test_student_cannot_download(): void
    {
        $course = $this->createCourse();

        $student = User::factory()->create([
            'is_admin' => false,
        ]);

        $this->actingAs($student)
            ->get(route('courses.roster.download', $course))
            ->assertForbidden();
    }

    public function test_admin_can_download_roster(): void
    {
        $course = $this->createCourse();

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $student = User::factory()->create([
            'name' => '田中',
            'email' => 'tanaka@example.com',
        ]);

        $this->createEntry(
            $course,
            $student,
            'approved',
            'A'
        );

        $response = $this->actingAs($admin)
            ->get(route('courses.roster.download', $course));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $response->assertDownload(
            "course-{$course->id}-roster.csv"
        );

        $content = $response->streamedContent();

        $this->assertStringStartsWith(
            "\xEF\xBB\xBFname,email,grade",
            $content
        );

        $this->assertStringContainsString(
            '田中,tanaka@example.com,A',
            $content
        );
    }

    public function test_only_approved_students_are_exported(): void
    {
        $course = $this->createCourse();
        $otherCourse = $this->createCourse();

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $approved = User::factory()->create();
        $pending = User::factory()->create();
        $rejected = User::factory()->create();
        $other = User::factory()->create();

        $this->createEntry($course, $approved, 'approved');
        $this->createEntry($course, $pending, 'pending');
        $this->createEntry($course, $rejected, 'rejected');
        $this->createEntry($otherCourse, $other, 'approved');

        $response = $this->actingAs($admin)
            ->get(route('courses.roster.download', $course));

        $response->assertOk();

        $content = $response->streamedContent();

        $this->assertStringContainsString(
            $approved->email . ",\n",
            $content
        );

        $this->assertStringNotContainsString(
            $pending->email,
            $content
        );

        $this->assertStringNotContainsString(
            $rejected->email,
            $content
        );

        $this->assertStringNotContainsString(
            $other->email,
            $content
        );
    }

    public function test_empty_roster_contains_only_header(): void
    {
        $course = $this->createCourse();

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('courses.roster.download', $course));

        $response->assertOk();

        $this->assertSame(
            "\xEF\xBB\xBFname,email,grade\n",
            $response->streamedContent()
        );
    }

    public function test_csv_formula_values_are_prefixed(): void
    {
        $course = $this->createCourse();

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $student = User::factory()->create([
            'name' => '=1+1',
            'email' => '+student@example.com',
        ]);

        $this->createEntry($course, $student, 'approved');

        $response = $this->actingAs($admin)
            ->get(route('courses.roster.download', $course));

        $response->assertOk();

        $this->assertStringContainsString(
            'name:=1+1,email:+student@example.com,',
            $response->streamedContent()
        );
    }

    public function test_roster_download_button_is_visible_only_to_admin_on_course_detail(): void
    {
        $course = $this->createCourse();
        $admin = User::factory()->create(['is_admin' => true]);
        $student = User::factory()->create(['is_admin' => false]);
        $this->createEntry($course, $student, 'approved');
        $action = 'action="' . route('courses.roster.download', $course) . '"';

        $this->actingAs($admin)->get("/courses/{$course->id}")
            ->assertOk()
            ->assertSee($action, false)
            ->assertSeeText('履修者名簿CSVをダウンロード');

        $this->actingAs($student)->get("/courses/{$course->id}")
            ->assertOk()
            ->assertDontSee($action, false)
            ->assertDontSeeText('履修者名簿CSVをダウンロード');
    }

    public function test_csv_preserves_japanese_names_with_commas_and_quotes(): void
    {
        $course = $this->createCourse();
        $admin = User::factory()->create(['is_admin' => true]);
        $student = User::factory()->create(['name' => '田中, "太郎"']);
        $this->createEntry($course, $student, 'approved', 'B');

        $response = $this->actingAs($admin)
            ->get(route('courses.roster.download', $course))
            ->assertOk();

        $this->assertSame(
            "\xEF\xBB\xBFname,email,grade\n" . '"田中, ""太郎""",' . $student->email . ",B\n",
            $response->streamedContent()
        );
    }

    public function test_csv_doubles_existing_reserved_prefixes(): void
    {
        $course = $this->createCourse();
        $admin = User::factory()->create(['is_admin' => true]);
        $student = User::factory()->create([
            'name' => 'name:田中',
            'email' => 'email:roster-prefix@example.test',
        ]);
        $this->createEntry($course, $student, 'approved');

        $response = $this->actingAs($admin)
            ->get(route('courses.roster.download', $course))
            ->assertOk();

        $this->assertSame(
            "\xEF\xBB\xBFname,email,grade\nname:name:田中,email:email:roster-prefix@example.test,\n",
            $response->streamedContent()
        );
    }
}
