<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timetable_entries', function (Blueprint $table) {
            $table->string('status')->default('pending');
            $table->timestamp('decided_at')->nullable();
        });

        // W9までの既存データを承認済みにする
        DB::table('timetable_entries')->update([
            'status' => 'approved',
        ]);
    }

    public function down(): void
    {
        Schema::table('timetable_entries', function (Blueprint $table) {
            $table->dropColumn(['status', 'decided_at']);
        });
    }
};
