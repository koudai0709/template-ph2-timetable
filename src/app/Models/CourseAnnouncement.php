<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseAnnouncement extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['course_id', 'title', 'body'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}