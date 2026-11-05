<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\Request;
use App\Models\Review;

class ReviewController extends Controller
{
    public function store(Request $request, Course $course)
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:1000'],
        ], [
            'body.required' => 'レビューを入力してください',
            'body.string' => 'レビューは文字列で入力してください',
            'body.max' => 'レビューは1000文字以内で入力してください',
        ]);

        $course->reviews()->create([
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        return redirect('/courses/' . $course->id);
    }
    public function destroy(Request $request, Review $review)
    {
        abort_unless($review->user_id === $request->user()->id, 403);

    $courseId = $review->course_id;

    $review->delete();

    return redirect('/courses/' . $courseId);
}
}