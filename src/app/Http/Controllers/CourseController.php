<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $courses = Course::with([
            'entries' => function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            },
        ])
            ->orderBy('day')
            ->orderBy('period')
            ->get();

        return view('courses.index', compact('courses'));
    }
}