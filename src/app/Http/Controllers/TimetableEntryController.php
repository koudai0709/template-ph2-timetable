<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TimetableEntry;

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
            'period' => ['required', 'integer', 'between:1,6'],
            'title' => ['required', 'string', 'max:255'],
            'room' => ['nullable', 'string', 'max:255'],
        ], [
            'required' => ':attributeを入力してください。',
            'integer' => ':attributeは整数で指定してください。',
            'between' => ':attributeは:min〜:maxの範囲で指定してください。',
            'string' => ':attributeは文字列で入力してください。',
            'max' => ':attributeは:max文字以内で入力してください。',
        ], [
            'day' => '曜日',
            'period' => '時限',
            'title' => '授業名',
            'room' => '教室',
        ]);

        TimetableEntry::create($validated);

        return redirect('/entries');
    }
}
