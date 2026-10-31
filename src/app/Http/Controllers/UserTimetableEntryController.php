<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserTimetableEntryController extends Controller
{
    public function index(Request $request)
    {
        $entries = $request->user()->entries()
            ->join('courses', 'timetable_entries.course_id', '=', 'courses.id')
            ->with('course')
            ->orderBy('courses.day')
            ->orderBy('courses.period')
            ->select('timetable_entries.*')
            ->get();

        return view('entries.index', compact('entries'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id' => [
                'required', 'integer', Rule::exists('courses', 'id'),
                Rule::unique('timetable_entries', 'course_id')->where('user_id', $request->user()->id),
            ],
        ], [
            'course_id.required' => '授業を選択してください。',
            'course_id.integer' => '授業を一覧から選んでください。',
            'course_id.exists' => '授業を一覧から選んでください。',
            'course_id.unique' => 'この授業はすでに登録されています。',
        ]);

        $course = Course::findOrFail($validated['course_id']);
        $exists = $request->user()->entries()->whereHas('course', function ($query) use ($course) {
            $query->where('day', $course->day)->where('period', $course->period);
        })->exists();

        if ($exists) {
            return back()->withErrors([
                'course_id' => 'その曜日・時限にはすでに授業が登録されています。',
            ])->withInput();
        }

        $request->user()->entries()->create($validated);

        return redirect('/entries');
    }
}
