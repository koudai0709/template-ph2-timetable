<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;

class TimetableController extends Controller
{
     // courses テーブルの day（1〜5）と、画面に出す曜日の名前の対応
    private array $dayNames = [1 => '月', 2 => '火', 3 => '水', 4 => '木', 5 => '金'];

    // 時間割の一覧ページ
    public function index()
    {
        $courses = DB::select('SELECT period,name,teacher,room FROM courses
WHERE day=? 
ORDER BY period ASC', [1]);
$requiredCourses = DB::select('SELECT name,credits
FROM courses
WHERE required=true
ORDER BY code ASC', []);

        return view('timetable', [
            'title' => '時間割',
            'dayNames' => $this->dayNames,
            'courses' => $courses,
            'requiredCourses' => $requiredCourses,
        ]);
    }
}
