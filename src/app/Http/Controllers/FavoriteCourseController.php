<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\Request;

class FavoriteCourseController extends Controller
{
    public function toggle(Request $request, Course $course)
    {
        $user = $request->user();

        $user->favoriteCourses()->toggle($course->id);

        return redirect()->back();
    }
}