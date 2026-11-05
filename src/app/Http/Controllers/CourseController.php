<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index(Request $request)
{
    $validated = $request->validate([
        'q' => ['nullable', 'string'],
    ]);

    $keyword = trim($validated['q'] ?? '');

    $courses = Course::with([
        'entries' => function ($query) use ($request) {
            $query->where('user_id', $request->user()->id);
        },
    ])
        ->when($keyword !== '', function ($query) use ($keyword) {
            $query->where(function ($query) use ($keyword) {
                $query->where('name', 'ilike', '%' . $keyword . '%')
                    ->orWhere('teacher', 'ilike', '%' . $keyword . '%');
            });
        })
        ->orderBy('day')
        ->orderBy('period')
        ->get();

    return view('courses.index', compact('courses', 'keyword'));
}
    public function show(Course $course)
    {
        $course->load('reviews.user');

        return view('courses.show', compact('course'));
    }
}