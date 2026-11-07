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
    ]);

    $keyword = trim($validated['q'] ?? '');
    $tagId = $validated['tag'] ?? null;

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
        ->orderBy('day')
        ->orderBy('period')
        ->get();

    $tags = Tag::orderBy('id')->get();

    logger()->info('courses SQL count (controller)', [
        'count' => count(DB::getQueryLog()),
    ]);
    return view('courses.index', compact('courses', 'keyword', 'tags', 'tagId'));
    }
    public function show(Course $course)
    {
    $course->load([
        'reviews.user',
        'tags',
        'students',
    ]);

        return view('courses.show', compact('course'));
    }
}