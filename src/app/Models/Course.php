<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Course extends Model
{
    public $timestamps = false;

    public function entries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
    public function tags(): BelongsToMany
    {
    return $this->belongsToMany(Tag::class, 'course_tag', 'course_id', 'tag_id');
    }

    public function students(): BelongsToMany
    {
    return $this->belongsToMany(
        User::class,
            'timetable_entries',
            'course_id',
            'user_id'
        );
    }
    public function favoritedByUsers(): BelongsToMany
    {
    return $this->belongsToMany(
        User::class,
        'favorite_courses',
        'course_id',
        'user_id'
    );
    }
}
