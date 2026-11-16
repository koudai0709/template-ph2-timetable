<?php

namespace App\Http\Controllers;

use App\Models\TimetableEntry;
use Illuminate\Support\Facades\DB;

class AdminEntryController extends Controller
{
    

public function index()
{
    // 管理者以外はアクセス禁止
    abort_unless(auth()->user()->is_admin, 403);

    // 申請中のデータだけ取得する
    $entries = TimetableEntry::with(['user', 'course'])
        ->where('status', 'pending')
        ->orderBy('created_at')
        ->get();

    // 管理者の申請一覧画面を表示する
    return view('admin.entries.index', compact('entries'));
}


public function reject($id)
{
    // 管理者以外は操作禁止
    abort_unless(auth()->user()->is_admin, 403);

    // 申請中の場合だけ却下する
    $updated = TimetableEntry::whereKey($id)
        ->where('status', 'pending')
        ->update([
            'status' => 'rejected',
            'decided_at' => now(),
        ]);

    // 更新できなかった場合は403
    abort_unless($updated === 1, 403);

    return redirect('/admin/entries');
}

    
    public function approve($id)
    {
    // 管理者以外は操作禁止
        abort_unless(auth()->user()->is_admin, 403);

        DB::transaction(function () use ($id) {
        // 申請を取得してロック
            $entry = TimetableEntry::findOrFail($id);

        // 対象授業をロック
            $course = \App\Models\Course::whereKey($entry->course_id)
                ->lockForUpdate()
                ->firstOrFail();

        // 申請をロックして再取得
            $entry = TimetableEntry::whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

        // 申請中以外は承認できない
            // 検証専用：状態チェックを外す

        // 承認済み人数を数える
            $approvedCount = TimetableEntry::where('course_id', $course->id)
                ->where('status', 'approved')
                ->count();

        // 定員に達していたら承認できない
            if ($approvedCount >= $course->capacity) {
                abort(422, 'この授業は定員に達しています。');
            }

        // 承認状態と日時を保存
            $entry->status = 'approved';
            $entry->decided_at = now();
            $entry->save();
        });

        return redirect('/admin/entries');
    }

}
