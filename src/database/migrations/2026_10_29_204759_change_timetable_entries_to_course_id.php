
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timetable_entries', function (Blueprint $table) {
            $table->dropUnique(['day', 'period']);
            $table->dropColumn(['day', 'period', 'title', 'room']);
            
            $table->foreignId('course_id')
                ->constrained('courses');

            $table->unique('course_id');
        });
    }

    public function down(): void
    {
        Schema::table('timetable_entries', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->dropUnique(['course_id']);
            $table->dropColumn('course_id');

            $table->integer('day');
            $table->integer('period');
            $table->string('title');
            $table->string('room')->nullable();

            $table->unique(['day', 'period']);
        });
    }
};
