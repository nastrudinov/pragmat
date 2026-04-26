<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mobilization extends Model
{
    protected $table = 'mobilizations';
    
    protected $fillable = [
        'title',
        'object_name',
        'start_date',
        'end_date',
        'status',
        'current_stage_id',
        'created_by'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the current stage of the mobilization
     */
    public function currentStage(): BelongsTo
    {
        return $this->belongsTo(MobilizationStage::class, 'current_stage_id');
    }

    /**
     * Get the creator of the mobilization
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    /**
     * Get the employees assigned to this mobilization
     */
    public function mobilizationEmployees(): HasMany
    {
        return $this->hasMany(MobilizationEmployee::class);
    }

    /**
     * Get the stage history for this mobilization
     */
    public function stageHistories(): HasMany
    {
        return $this->hasMany(StageHistory::class);
    }
}