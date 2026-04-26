<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MobilizationStage extends Model
{
    public $timestamps = false;
    
    protected $fillable = [
        'name',
        'sla_hours',
        'description',
        'sort_order'
    ];
    
    protected $casts = [
        'sla_hours' => 'integer',
        'sort_order' => 'integer'
    ];
    
    public function stageHistories(): HasMany
    {
        return $this->hasMany(StageHistory::class, 'stage_id');
    }
    
    public function mobilizations(): HasMany
    {
        return $this->hasMany(Mobilization::class, 'current_stage_id');
    }
}