<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PositionCourseRequirement extends Model
{
    protected $fillable = [
        'position_id',
        'course_id',
        'is_required'
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}