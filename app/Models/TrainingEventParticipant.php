<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingEventParticipant extends Model
{
    protected $table = 'training_event_participants';

    protected $fillable = [
        'event_id',
        'employee_id',
        'status',
        'completion_date',
        'certificate_number',
        'notes'
    ];

    protected $casts = [
        'completion_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(TrainingEvent::class, 'event_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}