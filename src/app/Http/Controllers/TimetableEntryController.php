<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TimetableEntry;
use Illuminate\Validation\Rule;

class TimetableEntryController extends Controller
{
    public function index()
    {
        $entries = TimetableEntry::orderBy('day')
            ->orderBy('period')
            ->get();
        return view('entries.index', compact('entries'));
    }

    public function create()
    {
        return view('entries.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'day' => ['required', 'integer', 'between:1,5'],
            'period' => [
                'required',
                'integer',
                'between:1,6',
                Rule::unique('timetable_entries', 'period')
                    ->where('day', $request->input('day')),
            ],
            'title' => ['required', 'string', 'max:255'],
            'room' => ['nullable', 'string', 'max:255'],
        ], [
            'required' => ':attributeを入力してください。',
            'integer' => ':attributeは整数で指定してください。',
            'between' => ':attributeは:min〜:maxの範囲で指定してください。',
            'string' => ':attributeは文字列で入力してください。',
            'max' => ':attributeは:max文字以内で入力してください。',
            'period.unique' => 'その曜日・時限にはすでに授業が登録されています。',
            'title.required' => '授業名を入力してください',
            'day.required' => '曜日は1〜5で入力してください',
            'day.integer' => '曜日は1〜5で入力してください',
            'day.between' => '曜日は1〜5で入力してください',
            'period.required' => '時限は1〜6で入力してください',
            'period.integer' => '時限は1〜6で入力してください',
            'period.between' => '時限は1〜6で入力してください',
        ], [
            'day' => '曜日',
            'period' => '時限',
            'title' => '授業名',
            'room' => '教室',
        ]);

        TimetableEntry::create($validated);

        return redirect('/entries');
    }
    public function edit($id)
    {
        $entry = TimetableEntry::findOrFail($id);

        return view('entries.edit', compact('entry'));
    }
    public function update(Request $request, $id)
    {
        $entry = TimetableEntry::findOrFail($id);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'room' => ['nullable', 'string', 'max:255'],
        ], [
            'title.required' => '授業名を入力してください',
        ]);

        $entry->update($validated);

        return redirect('/entries');
    }

    public function destroy($id)
    {
        $entry = TimetableEntry::findOrFail($id);
        $entry->delete();

        return redirect('/entries');
    }
}
