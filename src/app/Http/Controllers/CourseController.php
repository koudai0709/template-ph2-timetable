<?php

namespace App\Http\Controllers;

use App\Models\Course;

class CourseController extends Controller
{
    public function index()
    {
        $courses = Course::with('entries')
            ->orderBy('day')
            ->orderBy('period')
            ->get();

        return view('courses.index', compact('courses'));
    }
}
