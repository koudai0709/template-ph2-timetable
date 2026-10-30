<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    public $timestamps = false;

    public function entries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class);
    }
}
