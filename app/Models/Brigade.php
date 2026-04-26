<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Brigade extends Model
{
    protected $fillable = [
        'name',
        'leader_employee_id'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function leader(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'leader_employee_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function courseRequirements(): HasMany
    {
        return $this->hasMany(BrigadeCourseRequirement::class);
    }
}