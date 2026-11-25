<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\TimetableEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class CourseGradeImportTest extends TestCase
{
    use DatabaseTransactions;

    private const HEADER = "name,email,grade\n";

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('timetable_test', DB::connection()->getDatabaseName());
    }

    public function test_only_admin_can_import_and_see_the_import_form(): void
    {
        $course = $this->course();
        $entry = $this->entry($course);
        $csv = self::HEADER . "氏名,{$entry->user->email},A\n";

        $this->upload($course, $csv)->assertRedirect('/login');
        $this->actingAs($entry->user);
        $this->upload($course, $csv)->assertForbidden();
        $this->assertNull($entry->refresh()->grade);

        $action = 'action="' . route('courses.grades.import', $course) . '"';
        $this->get("/courses/{$course->id}")->assertOk()->assertDontSee($action, false);
        $this->asAdmin();
        $this->get("/courses/{$course->id}")->assertOk()
            ->assertSee($action, false)
            ->assertSeeText('成績を登録する')
            ->assertSeeText('CSV UTF-8（コンマ区切り）');
    }

    public function test_admin_imports_all_grades_without_bom_and_preserves_other_records(): void
    {
        $this->asAdmin();
        $course = $this->course();
        $omitted = $this->entry($course, grade: 'C');
        $csv = self::HEADER;
        $expected = [];
        foreach (['A', 'B', 'C', 'D', 'F'] as $grade) {
            $entry = $this->entry($course, grade: 'D');
            $expected[$entry->id] = $grade;
            $csv .= "CSV上の氏名,{$entry->user->email},{$grade}\n";
        }

        $this->upload($course, $csv)->assertRedirect("/courses/{$course->id}")
            ->assertSessionHas('grade_import_count', 5);
        $this->get("/courses/{$course->id}")->assertSeeText('成績を5件登録しました。');
        foreach ($expected as $id => $grade) {
            $this->assertDatabaseHas('timetable_entries', ['id' => $id, 'grade' => $grade]);
        }
        $this->assertSame('C', $omitted->refresh()->grade);
    }

    public function test_admin_imports_csv_exported_by_step_one_with_bom_and_escaped_fields(): void
    {
        $this->asAdmin();
        $course = $this->course();
        $quoted = $this->entry($course, grade: 'A', user: [
            'name' => "田中, \"太郎\"\n二行目",
            'email' => '+student@example.test',
        ]);
        $reserved = $this->entry($course, grade: 'B', user: [
            'email' => 'email:reserved@example.test',
        ]);
        $export = $this->get(route('courses.roster.download', $course))->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $export);
        $this->assertStringContainsString('email:+student@example.test', $export);
        $this->assertStringContainsString('email:email:reserved@example.test', $export);
        $quoted->grade = null;
        $quoted->save();
        $reserved->grade = null;
        $reserved->save();

        $this->upload($course, $export)->assertSessionHas('grade_import_count', 2);
        $this->assertSame('A', $quoted->refresh()->grade);
        $this->assertSame('B', $reserved->refresh()->grade);
    }

    #[DataProvider('invalidCsvCases')]
    public function test_invalid_csv_is_rejected(string $csv, string $reason): void
    {
        $this->asAdmin();
        $course = $this->course();
        $entry = $this->entry($course, grade: 'D', user: ['email' => 'student@example.test']);

        $this->assertImportError($this->upload($course, $csv), $reason);
        $this->assertSame('D', $entry->refresh()->grade);
    }

    public static function invalidCsvCases(): array
    {
        return [
            '空ファイル' => ['', '1行目：CSVファイルが空です。'],
            'ヘッダーのみ' => [self::HEADER, '2行目：登録する成績のデータ行がありません。'],
            '不正なヘッダー' => ["email,name,grade\n", '1行目：ヘッダーは name,email,grade の順で指定してください。'],
            '不正な列数' => [self::HEADER . "氏名,student@example.test\n", '2行目：列数は3列にしてください。'],
            '閉じ忘れ' => [self::HEADER . "\"氏名,student@example.test,A\n", '2行目：CSVの引用符または形式が不正です。'],
            '引用符の後ろに文字' => [self::HEADER . "\"氏名\"x,student@example.test,A\n", '2行目：CSVの引用符または形式が不正です。'],
            '非引用フィールドの引用符' => [self::HEADER . "氏\"名,student@example.test,A\n", '2行目：CSVの引用符または形式が不正です。'],
            '不正な文字コード' => [self::HEADER . "\x82\xA0,student@example.test,A\n", '2行目：文字コードはUTF-8で保存してください。'],
            '不正な成績' => [self::HEADER . "氏名,student@example.test,E\n", '2行目：成績はA・B・C・D・Fのいずれかを指定してください。'],
            '成績が空' => [self::HEADER . "氏名,student@example.test,\n", '2行目：成績が空欄です。'],
            'メールが空' => [self::HEADER . "氏名,,A\n", '2行目：メールアドレスが空欄です。'],
            '存在しないメール' => [self::HEADER . "氏名,missing@example.test,A\n", '2行目：対象授業の承認済み履修者に一致するメールアドレスがありません。'],
            '大文字の異なるメール' => [self::HEADER . "氏名,Student@example.test,A\n", '2行目：対象授業の承認済み履修者に一致するメールアドレスがありません。'],
        ];
    }

    public function test_pending_rejected_unenrolled_and_other_course_students_are_rejected(): void
    {
        $this->asAdmin();
        $course = $this->course();
        $users = [
            $this->entry($course, status: 'pending')->user,
            $this->entry($course, status: 'rejected')->user,
            User::factory()->create(),
            $this->entry($this->course())->user,
        ];
        $csv = self::HEADER;
        foreach ($users as $user) {
            $csv .= "氏名,{$user->email},A\n";
        }

        $response = $this->upload($course, $csv);
        for ($line = 2; $line <= 5; $line++) {
            $this->assertImportError($response, "{$line}行目：対象授業の承認済み履修者に一致するメールアドレスがありません。");
        }
        $this->assertDatabaseMissing('timetable_entries', ['course_id' => $course->id, 'grade' => 'A']);
    }

    public function test_duplicate_emails_are_rejected_after_prefix_restoration(): void
    {
        $this->asAdmin();
        $course = $this->course();
        $entry = $this->entry($course, grade: 'D', user: ['email' => '+duplicate@example.test']);
        $email = $entry->user->email;

        foreach ([$email, 'email:' . $email] as $secondEmail) {
            $csv = self::HEADER . "氏名,{$email},A\n氏名,{$secondEmail},B\n";
            $this->assertImportError($this->upload($course, $csv), '3行目：メールアドレスが2行目と重複しています。');
            $this->assertSame('D', $entry->refresh()->grade);
        }
    }

    public function test_multiline_csv_reports_physical_line_and_leaves_valid_rows_unchanged(): void
    {
        $this->asAdmin();
        $course = $this->course();
        $entry = $this->entry($course, grade: 'D');
        $invalid = $this->entry($course, grade: 'B');
        $csv = "name,email,grade\r\n\"氏名, \"\"引用\"\"\r\n続き\",{$entry->user->email},A\r\n氏名,{$invalid->user->email},E\r\n";

        $response = $this->upload($course, $csv);
        $message = '4行目：成績はA・B・C・D・Fのいずれかを指定してください。';
        $this->assertImportError($response, $message);
        $this->get("/courses/{$course->id}")->assertSeeText($message);
        $this->assertSame('D', $entry->refresh()->grade);
        $this->assertSame('B', $invalid->refresh()->grade);
    }

    public function test_csv_file_larger_than_five_mb_is_rejected(): void
    {
        $this->asAdmin();
        $course = $this->course();

        $response = $this->from("/courses/{$course->id}")
            ->post(route('courses.grades.import', $course), [
                'grade_csv' => UploadedFile::fake()->create('grades.csv', 5121, 'text/csv'),
            ]);
        $this->assertImportError($response, '成績CSVは5MB以下にしてください。');
    }

    public function test_database_failure_rolls_back_earlier_grade_updates(): void
    {
        $this->asAdmin();
        $course = $this->course();
        $first = $this->entry($course, grade: 'D');
        $second = $this->entry($course, grade: 'C');

        // 2件目のSQLが実行された後に失敗させ、両方の変更を巻き戻せるか確認する。
        TimetableEntry::updated(function (TimetableEntry $entry) use ($second): void {
            if ($entry->is($second) && $entry->wasChanged('grade')) {
                throw new RuntimeException('テスト用の成績更新失敗');
            }
        });

        $csv = self::HEADER . "氏名,{$first->user->email},A\n氏名,{$second->user->email},B\n";
        $this->assertImportError(
            $this->upload($course, $csv),
            '3行目付近：DB更新に失敗したため、成績を登録しませんでした。'
        );
        $this->assertSame('D', $first->refresh()->grade);
        $this->assertSame('C', $second->refresh()->grade);
    }

    private function asAdmin(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    private function course(): Course
    {
        $course = new Course();
        $course->forceFill([
            'code' => Str::uuid()->toString(),
            'name' => '成績CSVテスト',
            'day' => 1,
            'period' => 2,
            'room' => 'J201',
            'credits' => 2,
            'required' => false,
            'capacity' => 40,
        ])->save();

        return $course;
    }

    private function entry(Course $course, string $status = 'approved', ?string $grade = null, array $user = []): TimetableEntry
    {
        $student = User::factory()->create(['is_admin' => false, ...$user]);
        $entry = $course->entries()->make(['user_id' => $student->id, 'status' => $status]);
        $entry->grade = $grade;
        $entry->save();

        return $entry;
    }

    private function upload(Course $course, string $csv): TestResponse
    {
        return $this->from("/courses/{$course->id}")
            ->post(route('courses.grades.import', $course), [
                'grade_csv' => UploadedFile::fake()->createWithContent('grades.csv', $csv),
            ]);
    }

    private function assertImportError(TestResponse $response, string $reason): void
    {
        $response->assertRedirect()->assertSessionHasErrors(['grade_csv' => $reason], errorBag: 'gradeImport')
            ->assertSessionMissing('grade_import_count');
    }
}
