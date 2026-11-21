<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Course;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\TimetableEntry;

class EnrollmentTest extends TestCase
{
    /**
     * A basic feature test example.
    */
    use DatabaseTransactions;
    public function test_guest_cannot_submit_enrollment(): void
    {
        $this->assertSame('timetable_test', DB::connection()->getDatabaseName());

        $course = Course::firstOrFail();
        $countBefore = $course->entries()->count();

        $response = $this->post('/entries', ['course_id' => $course->id]);

        $response->assertRedirect('/login');
        $this->assertSame($countBefore, $course->entries()->count());
    }
    public function test_student_can_submit_one_pending_enrollment(): void
    {
        $this->assertSame('timetable_test', DB::connection()->getDatabaseName());

        $student = User::factory()->create(['is_admin' => false]);
        $course = Course::firstOrFail();

        $response = $this->actingAs($student)
            ->post('/entries', ['course_id' => $course->id]);

        $response->assertRedirect('/entries');
        $response->assertSessionHasNoErrors();

        $this->assertSame(1, $student->entries()->count());
        $this->assertDatabaseHas('timetable_entries', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'pending',
        ]);
    }
    public function test_student_cannot_resubmit_rejected_enrollment(): void
    {
        $this->assertSame('timetable_test', DB::connection()->getDatabaseName());

        $student = User::factory()->create(['is_admin' => false]);
        $course = Course::firstOrFail();

        $entry = new TimetableEntry();
        $entry->user_id = $student->id;
        $entry->course_id = $course->id;
        $entry->status = 'rejected';
        $entry->decided_at = now()->subDay();
        $entry->save();

        $response = $this->actingAs($student)
            ->from('/entries/create')
            ->post('/entries', ['course_id' => $course->id]);

        $response->assertRedirect('/entries/create');
        $response->assertSessionHasErrors('course_id');

        $this->assertSame(1, $student->entries()->count());
        $this->assertSame('rejected', $entry->refresh()->status);
    }
    public function test_student_can_cancel_own_pending_enrollment(): void
{
    $this->assertSame('timetable_test', DB::connection()->getDatabaseName());

    $student = User::factory()->create(['is_admin' => false]);
    $course = Course::firstOrFail();

    $entry = new TimetableEntry();
    $entry->user_id = $student->id;
    $entry->course_id = $course->id;
    $entry->status = 'pending';
    $entry->save();

    $response = $this->actingAs($student)
        ->delete("/entries/{$entry->id}");

    $response->assertRedirect('/entries');
    $this->assertDatabaseMissing('timetable_entries', ['id' => $entry->id]);
}
public function test_student_cannot_cancel_another_students_enrollment(): void
{
    $this->assertSame('timetable_test', DB::connection()->getDatabaseName());

    $owner = User::factory()->create(['is_admin' => false]);
    $otherStudent = User::factory()->create(['is_admin' => false]);
    $course = Course::firstOrFail();

    $entry = new TimetableEntry();
    $entry->user_id = $owner->id;
    $entry->course_id = $course->id;
    $entry->status = 'pending';
    $entry->save();

    $response = $this->actingAs($otherStudent)
        ->delete("/entries/{$entry->id}");

    $response->assertForbidden();
    $this->assertDatabaseHas('timetable_entries', [
        'id' => $entry->id,
        'user_id' => $owner->id,
        'status' => 'pending',
    ]);
}
public function test_student_cannot_cancel_approved_enrollment(): void
{
    $this->assertSame('timetable_test', DB::connection()->getDatabaseName());

    $student = User::factory()->create(['is_admin' => false]);
    $course = Course::firstOrFail();

    $entry = new TimetableEntry();
    $entry->user_id = $student->id;
    $entry->course_id = $course->id;
    $entry->status = 'approved';
    $entry->decided_at = now();
    $entry->save();

    $response = $this->actingAs($student)
        ->delete("/entries/{$entry->id}");

    $response->assertForbidden();
    $this->assertDatabaseHas('timetable_entries', [
        'id' => $entry->id,
        'user_id' => $student->id,
        'status' => 'approved',
    ]);
}
    public function test_student_cannot_open_approval_page(): void
    {
        $this->assertSame('timetable_test', DB::connection()->getDatabaseName());

        $student = User::factory()->create(['is_admin' => false]);

        $this->actingAs($student)->get('/admin/entries')->assertForbidden();
    }

public function test_student_cannot_approve_enrollment(): void
{
    $this->assertSame('timetable_test', DB::connection()->getDatabaseName());

    $student = User::factory()->create(['is_admin' => false]);
    $course = Course::firstOrFail();

    $entry = new TimetableEntry();
    $entry->user_id = $student->id;
    $entry->course_id = $course->id;
    $entry->status = 'pending';
    $entry->save();

    $response = $this->actingAs($student)
        ->post("/admin/entries/{$entry->id}/approve");

    $response->assertForbidden();
    $this->assertDatabaseHas('timetable_entries', [
        'id' => $entry->id,
        'status' => 'pending',
        'decided_at' => null,
    ]);
}
public function test_admin_can_approve_pending_enrollment(): void
{
    $this->assertSame('timetable_test', DB::connection()->getDatabaseName());

    $student = User::factory()->create(['is_admin' => false]);
    $admin = User::factory()->create(['is_admin' => true]);
    $course = Course::firstOrFail();

    // 承認できるように、テスト中だけ定員に空きを作る。
    $approvedCount = $course->entries()->where('status', 'approved')->count();
    $course->capacity = $approvedCount + 2;
    $course->save();

    $entry = new TimetableEntry();
    $entry->user_id = $student->id;
    $entry->course_id = $course->id;
    $entry->status = 'pending';
    $entry->save();

    $response = $this->actingAs($admin)
        ->post("/admin/entries/{$entry->id}/approve");

    $response->assertRedirect('/admin/entries');

    $entry->refresh();
    $this->assertSame('approved', $entry->status);
    $this->assertNotNull($entry->decided_at);
}
public function test_admin_can_approve_when_one_seat_remains(): void
{
    $this->assertSame('timetable_test', DB::connection()->getDatabaseName());

    $student = User::factory()->create(['is_admin' => false]);
    $admin = User::factory()->create(['is_admin' => true]);
    $course = Course::firstOrFail();

    $approvedCount = $course->entries()->where('status', 'approved')->count();
    $capacity = $approvedCount + 1;
    $course->capacity = $capacity;
    $course->save();

    $entry = new TimetableEntry();
    $entry->user_id = $student->id;
    $entry->course_id = $course->id;
    $entry->status = 'pending';
    $entry->save();

    $response = $this->actingAs($admin)
        ->post("/admin/entries/{$entry->id}/approve");

    $response->assertRedirect('/admin/entries');
    $this->assertSame('approved', $entry->refresh()->status);
    $this->assertNotNull($entry->decided_at);
    $this->assertSame(
        $capacity,
        $course->entries()->where('status', 'approved')->count()
    );
}
public function test_admin_cannot_approve_when_course_is_full(): void
{
    $this->assertSame('timetable_test', DB::connection()->getDatabaseName());

    $admin = User::factory()->create(['is_admin' => true]);
    $approvedStudent = User::factory()->create(['is_admin' => false]);
    $pendingStudent = User::factory()->create(['is_admin' => false]);
    $course = Course::firstOrFail();

    // 承認済みの申請を1件用意し、現在の承認済み人数を定員にする。
    $approvedEntry = new TimetableEntry();
    $approvedEntry->user_id = $approvedStudent->id;
    $approvedEntry->course_id = $course->id;
    $approvedEntry->status = 'approved';
    $approvedEntry->decided_at = now();
    $approvedEntry->save();

    $approvedCount = $course->entries()->where('status', 'approved')->count();
    $course->capacity = $approvedCount;
    $course->save();

    $pendingEntry = new TimetableEntry();
    $pendingEntry->user_id = $pendingStudent->id;
    $pendingEntry->course_id = $course->id;
    $pendingEntry->status = 'pending';
    $pendingEntry->save();

    $response = $this->actingAs($admin)
        ->post("/admin/entries/{$pendingEntry->id}/approve");

    $response->assertStatus(422);
    $this->assertDatabaseHas('timetable_entries', [
        'id' => $pendingEntry->id,
        'status' => 'pending',
        'decided_at' => null,
    ]);
    $this->assertSame(
        $approvedCount,
        $course->entries()->where('status', 'approved')->count()
    );
}
public function test_admin_cannot_approve_rejected_enrollment(): void
{
    $this->assertSame('timetable_test', DB::connection()->getDatabaseName());

    $admin = User::factory()->create(['is_admin' => true]);
    $student = User::factory()->create(['is_admin' => false]);
    $course = Course::firstOrFail();

    $approvedCount = $course->entries()->where('status', 'approved')->count();
    $course->capacity = $approvedCount + 1;
    $course->save();

    $entry = new TimetableEntry();
    $entry->user_id = $student->id;
    $entry->course_id = $course->id;
    $entry->status = 'rejected';
    $entry->decided_at = now()->subDay();
    $entry->save();

    $decidedAt = $entry->refresh()->getRawOriginal('decided_at');

    $response = $this->actingAs($admin)
        ->post("/admin/entries/{$entry->id}/approve");

    $response->assertForbidden();

    $entry->refresh();
    $this->assertSame('rejected', $entry->status);
    $this->assertSame($decidedAt, $entry->getRawOriginal('decided_at'));
}
}
