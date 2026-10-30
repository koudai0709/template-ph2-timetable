<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TimetableEntry;
use Illuminate\Validation\Rule;
use App\Models\Course;

class TimetableEntryController extends Controller
{
   
    public function index()
    {
        $entries = TimetableEntry::query()
            ->join('courses', 'timetable_entries.course_id', '=', 'courses.id')
            ->with('course')
            ->orderBy('courses.day')
            ->orderBy('courses.period')
            ->select('timetable_entries.*')
            ->get();

        return view('entries.index', compact('entries'));
    }


    
    public function create()
    {
        $courses = Course::orderBy('day')
            ->orderBy('period')
            ->get();

        return view('entries.create', compact('courses'));
    }


    
    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id' => [
                'required',
                'integer',
                Rule::exists('courses', 'id'),
                Rule::unique('timetable_entries', 'course_id'),
            ],
        ], [
            'course_id.required' => '授業を選択してください。',
            'course_id.integer' => '授業を一覧から選んでください。',
            'course_id.exists' => '授業を一覧から選んでください。',
            'course_id.unique' => 'この授業はすでに登録されています。',
        ]);

        $course = Course::findOrFail($validated['course_id']);
            $exists = TimetableEntry::whereHas('course', function ($query) use ($course) {
                $query->where('day', $course->day)
                    ->where('period', $course->period);
            })->exists();
        if ($exists) {
            return back()
                ->withErrors([
                    'course_id' => 'その曜日・時限にはすでに授業が登録されています。',
                ])
                ->withInput();
        }
        TimetableEntry::create($validated);
        return redirect('/entries');
}


    public function destroy($id)
    {
        $entry = TimetableEntry::findOrFail($id);
        $entry->delete();

        return redirect('/entries');
    }
}

