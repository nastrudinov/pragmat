<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrigadeCourseRequirement extends Model
{
    protected $fillable = [
        'brigade_id',
        'course_id',
        'is_required'
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    public function brigade(): BelongsTo
    {
        return $this->belongsTo(Brigade::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}