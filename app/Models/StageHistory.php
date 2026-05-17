<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StageHistory extends Model
{
    protected $fillable = [
        'mobilization_id',
        'stage_id',
        'started_at',
        'completed_at',
        'status',
        'notes'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function mobilization(): BelongsTo
    {
        return $this->belongsTo(Mobilization::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(MobilizationStage::class, 'stage_id');
    }
}