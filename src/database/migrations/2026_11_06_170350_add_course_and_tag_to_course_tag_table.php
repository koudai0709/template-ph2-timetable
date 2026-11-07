<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_tag', function (Blueprint $table) {
            $table->foreignId('course_id')
                ->constrained('courses')
                ->cascadeOnDelete();

            $table->foreignId('tag_id')
                ->constrained('tags')
                ->cascadeOnDelete();

            $table->unique(['course_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::table('course_tag', function (Blueprint $table) {
            $table->dropUnique(['course_id', 'tag_id']);
            $table->dropConstrainedForeignId('tag_id');
            $table->dropConstrainedForeignId('course_id');
        });
    }
};
