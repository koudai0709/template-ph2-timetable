<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CourseSearchTest extends TestCase
{
    use DatabaseTransactions;

    private string $keyword;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('timetable_test', DB::connection()->getDatabaseName());
        $this->keyword = 'course-search-' . Str::uuid();
    }

    public function test_guest_cannot_access_course_list(): void
    {
        $this->get('/courses')->assertRedirect('/login');
    }

    public function test_courses_can_be_filtered_by_day_and_period(): void
    {
        $this->actingAs(User::factory()->create());
        $match = $this->createCourse();
        $differentDay = $this->createCourse(['day' => 2]);
        $differentPeriod = $this->createCourse(['period' => 3]);

        $this->assertCourseIds([$match->id, $differentPeriod->id], $this->search(['day' => 1]));
        $this->assertCourseIds([$match->id, $differentDay->id], $this->search(['period' => 2]));
        $this->assertCourseIds([$match->id], $this->search(['day' => 1, 'period' => 2]));
    }

    public function test_keyword_tag_day_and_period_filters_can_be_combined(): void
    {
        $this->actingAs(User::factory()->create());
        $tag = Tag::create(['name' => $this->keyword]);
        $nameMatch = $this->createCourse();
        $teacherMatch = $this->createCourse(['name' => '別の授業', 'teacher' => $this->keyword]);
        $wrongKeyword = $this->createCourse(['name' => '別の授業']);
        $wrongDay = $this->createCourse(['day' => 2, 'name' => '別の授業', 'teacher' => $this->keyword]);
        $wrongPeriod = $this->createCourse(['period' => 3]);
        $this->createCourse();

        foreach ([$nameMatch, $teacherMatch, $wrongKeyword, $wrongDay, $wrongPeriod] as $course) {
            $course->tags()->attach($tag);
        }

        $response = $this->search(['tag' => $tag->id, 'day' => 1, 'period' => 2]);

        $this->assertCourseIds([$nameMatch->id, $teacherMatch->id], $response);
    }

    public function test_available_filter_excludes_full_courses(): void
    {
        $this->actingAs(User::factory()->create());
        $student = User::factory()->create();
        $available = $this->createCourse(['capacity' => 2]);
        $full = $this->createCourse(['capacity' => 1]);
        $this->addEntry($student, $available, 'approved');
        $this->addEntry($student, $full, 'approved');

        $this->assertCourseIds([$available->id], $this->search(['available' => 1]));
        $this->assertCourseIds([$available->id, $full->id], $this->search());
    }

    public function test_pending_and_rejected_entries_do_not_count_toward_capacity(): void
    {
        $this->actingAs(User::factory()->create());
        $course = $this->createCourse(['capacity' => 2]);

        foreach (['approved', 'pending', 'rejected'] as $status) {
            $this->addEntry(User::factory()->create(), $course, $status);
        }

        $this->assertCourseIds([$course->id], $this->search(['available' => 1]));
    }

    public function test_course_list_shows_twenty_courses_per_page(): void
    {
        $this->actingAs(User::factory()->create());
        $last = $this->createCourse(['day' => 2, 'period' => 1]);
        $twentieth = $this->createCourse(['period' => 3]);
        $firstPageIds = [];

        for ($index = 0; $index < 19; $index++) {
            $firstPageIds[] = $this->createCourse()->id;
        }

        $firstPageIds[] = $twentieth->id;
        $response = $this->search();

        $this->assertCourseIds($firstPageIds, $response);
        $this->assertSame(20, $response->viewData('courses')->perPage());
        $this->assertCourseIds([$last->id], $this->search(['page' => 2]));
    }

    public function test_search_filters_are_preserved_when_paginating(): void
    {
        $this->actingAs(User::factory()->create());
        $tag = Tag::create(['name' => $this->keyword]);
        $full = $this->createCourse(['capacity' => 1]);
        $full->tags()->attach($tag);
        $this->addEntry(User::factory()->create(), $full, 'approved');

        for ($index = 0; $index < 20; $index++) {
            $this->createCourse()->tags()->attach($tag);
        }

        $last = $this->createCourse();
        $last->tags()->attach($tag);
        $filters = ['tag' => $tag->id, 'day' => 1, 'period' => 2, 'available' => 1];
        $firstPage = $this->search($filters);
        $nextUrl = $firstPage->viewData('courses')->nextPageUrl();

        $this->assertNotNull($nextUrl);
        $firstPage->assertSee($nextUrl);
        parse_str(parse_url($nextUrl, PHP_URL_QUERY), $query);
        foreach (['q' => $this->keyword, ...$filters, 'page' => 2] as $name => $value) {
            $this->assertSame((string) $value, $query[$name] ?? null);
        }

        $this->assertCourseIds([$last->id], $this->get($nextUrl)->assertOk());
    }

    public function test_search_returns_empty_results_without_error(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->search()->assertSeeText('該当する授業はありません。');

        $this->assertCourseIds([], $response);
    }

    private function createCourse(array $attributes = []): Course
    {
        $course = new Course();
        $course->forceFill(array_replace([
            'code' => (string) Str::uuid(),
            'name' => $this->keyword,
            'teacher' => 'テスト担当者',
            'day' => 1,
            'period' => 2,
            'room' => 'J201',
            'credits' => 2,
            'required' => false,
            'capacity' => 40,
        ], $attributes));
        $course->save();

        return $course;
    }

    private function addEntry(User $student, Course $course, string $status): void
    {
        $entry = $student->entries()->make(['course_id' => $course->id]);
        $entry->status = $status;
        $entry->decided_at = $status === 'pending' ? null : now();
        $entry->save();
    }

    private function search(array $filters = []): TestResponse
    {
        return $this->get('/courses?' . http_build_query(['q' => $this->keyword, ...$filters]))
            ->assertOk();
    }

    private function assertCourseIds(array $expected, TestResponse $response): void
    {
        $this->assertSame($expected, $response->viewData('courses')->getCollection()->modelKeys());
    }
}
