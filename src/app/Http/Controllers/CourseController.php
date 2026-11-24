<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\Request;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;

class CourseController extends Controller
{
    public function index(Request $request)
{
    DB::enableQueryLog();
    $validated = $request->validate([
        'q' => ['nullable', 'string'],
        'tag' => ['nullable', 'integer', 'exists:tags,id'],
        'day' => ['nullable', 'integer', 'between:1,5'],
        'period' => ['nullable', 'integer', 'between:1,6'],
        'available' => ['sometimes', 'boolean'],
    ]);

    $keyword = trim($validated['q'] ?? '');
    $tagId = $validated['tag'] ?? null;
    $day = $validated['day'] ?? null;
    $period = $validated['period'] ?? null;
    $availableOnly = (bool) ($validated['available'] ?? false);

    $courses = Course::with([
        'tags',
        'entries' => function ($query) use ($request) {
            $query->where('user_id', $request->user()->id);
        },
        'favoritedByUsers' => function ($query) use ($request) {
            $query->where('users.id', $request->user()->id);
        },
    ])
        ->withCount('favoritedByUsers')
        ->when($keyword !== '', function ($query) use ($keyword) {
            $query->where(function ($query) use ($keyword) {
                $query->where('name', 'ilike', '%' . $keyword . '%')
                    ->orWhere('teacher', 'ilike', '%' . $keyword . '%');
            });
        })
        ->when($tagId !== null, function ($query) use ($tagId) {
            $query->whereHas('tags', function ($tagQuery) use ($tagId) {
                $tagQuery->where('tags.id', $tagId);
            });
        })
        ->when($day !== null, function ($query) use ($day) {
            $query->where('day', $day);
        })
        ->when($period !== null, function ($query) use ($period) {
            $query->where('period', $period);
        })
        ->when($availableOnly, function ($query) {
            $query->has('students', '<', DB::raw('courses.capacity'));
        })
        ->orderBy('day')
        ->orderBy('period')
        ->orderBy('id')
        ->paginate(20)
        ->withQueryString();

    $tags = Tag::orderBy('id')->get();

    logger()->info('courses SQL count (controller)', [
        'count' => count(DB::getQueryLog()),
    ]);
    return view('courses.index', compact('courses', 'keyword', 'tags', 'tagId', 'day', 'period', 'availableOnly'));
    }
    public function show(Course $course)
    {
    $course->load([
        'reviews.user',
        'tags',
        'students',
        'announcements' => fn ($query) => $query->orderByDesc('created_at')->orderByDesc('id'),
    ]);

        return view('courses.show', compact('course'));
    }
}
