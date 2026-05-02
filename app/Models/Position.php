<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    protected $fillable = [
        'name',
        'category_id'
    ];
    
    public function category(): BelongsTo
    {
        return $this->belongsTo(PositionCategory::class, 'category_id');
    }
    
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
    
    public function courseRequirements(): HasMany
    {
        return $this->hasMany(PositionCourseRequirement::class);
    }
    
    // Получить все подразделения, где есть сотрудники с этой должностью
    public function getDepartmentsAttribute()
    {
        return Department::whereHas('employees', function($query) {
            $query->where('position_id', $this->id);
        })->get();
    }
}