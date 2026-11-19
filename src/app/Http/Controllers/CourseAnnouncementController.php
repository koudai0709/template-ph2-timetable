<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Course;
use App\Models\CourseAnnouncement;
use App\Mail\CourseAnnouncementMail;
use Illuminate\Support\Facades\Mail;

class CourseAnnouncementController extends Controller
{
    public function store(Request $request, Course $course)
{
    $validated = $request->validate([
        'title' => ['required', 'string', 'max:50'],
        'body' => ['required', 'string'],
    ]);

    $announcement = CourseAnnouncement::create([
    'course_id' => $course->id,
    'title' => $validated['title'],
    'body' => $validated['body'],
]);

foreach ($course->students as $student) {
    Mail::to($student->email)->send(new CourseAnnouncementMail($announcement));
}

    return redirect()->to("/courses/{$course->id}")
        ->with('success', 'お知らせを投稿しました');
}//
}
