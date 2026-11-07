<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tag;
use App\Models\Course;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        Tag::firstOrCreate(['name' => '演習あり']);
        Tag::firstOrCreate(['name' => '少人数']);
        Tag::firstOrCreate(['name' => '英語で実施']);
        Tag::firstOrCreate(['name' => 'レポート評価']);
        Tag::firstOrCreate(['name' => '1年生向け']);
    $assignments = [
    '演習あり' => ['CS101', 'PG220', 'DS230', 'PG120', 'UX250', 'BZ120'],
    '少人数' => ['EN101', 'EN102', 'CN100', 'UX250', 'SE300'],
    '英語で実施' => ['EN101', 'EN102'],
    'レポート評価' => ['IE110', 'SO120', 'HI130', 'LI140', 'CA200', 'ART100'],
    '1年生向け' => ['CS101', 'EC100', 'IE110', 'EN101', 'PS100', 'LW110'],
];

foreach ($assignments as $tagName => $courseCodes) {
    $tag = Tag::where('name', $tagName)->first();

    foreach ($courseCodes as $courseCode) {
        $course = Course::where('code', $courseCode)->first();

        if ($course && $tag) {
            $course->tags()->syncWithoutDetaching([$tag->id]);
        }
    }
}
        }
}
