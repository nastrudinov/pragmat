<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingEvent extends Model
{
    protected $fillable = [
        'title',
        'course_id',
        'format',
        'start_date',
        'end_date',
        'location',
        'training_center',
        'status',
        'cost',
        'max_participants',
        'notes'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'cost' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(TrainingEventParticipant::class, 'event_id');
    }

    public function getParticipantCountAttribute()
    {
        return $this->participants()->count();
    }

    public function getAvailableSlotsAttribute()
    {
        if (!$this->max_participants) {
            return null;
        }
        return max(0, $this->max_participants - $this->participant_count);
    }
}