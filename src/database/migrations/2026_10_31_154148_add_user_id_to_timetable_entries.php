<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timetable_entries', function (Blueprint $table) {
            // 以前の「授業ごとに1件だけ」の制約を削除
            $table->dropUnique('timetable_entries_course_id_unique');

            // ユーザーIDを追加し、usersテーブルと関連付ける
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // 同じユーザーが同じ授業を重複登録できないようにする
            $table->unique(['user_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::table('timetable_entries', function (Blueprint $table) {
            // 複合一意制約を削除
            $table->dropUnique('timetable_entries_user_id_course_id_unique');

            // 外部キー制約とuser_idカラムを削除
            $table->dropConstrainedForeignId('user_id');

            // 元の一意制約を復元
            $table->unique('course_id');
        });
    }
};
