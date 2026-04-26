<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $fillable = [
        'name',
        'category_id',
        'duration_hours',
        'periodicity_months',
        'description'
    ];

    protected $casts = [
        'duration_hours' => 'integer',
        'periodicity_months' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(CourseCategory::class, 'category_id');
    }

    public function employeeCourses(): HasMany
    {
        return $this->hasMany(EmployeeCourse::class);
    }

    public function positionRequirements(): HasMany
    {
        return $this->hasMany(PositionCourseRequirement::class);
    }

    public function brigadeRequirements(): HasMany
    {
        return $this->hasMany(BrigadeCourseRequirement::class);
    }
}