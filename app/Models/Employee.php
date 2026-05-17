<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    protected $fillable = [
        'personnel_number',  // Добавлено
        'full_name', 
        'last_name', 
        'first_name', 
        'middle_name',
        'position_id', 
        'brigade_id', 
        'department_id', 
        'email', 
        'phone', 
        'status'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];


    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function brigade(): BelongsTo
    {
        return $this->belongsTo(Brigade::class);
    }

    // Добавляем отношение к подразделению
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function userAccount(): HasOne
    {
        return $this->hasOne(UserAccount::class);
    }

    public function employeeCourses(): HasMany
    {
        return $this->hasMany(EmployeeCourse::class);
    }

    public function mobilizations(): HasMany
    {
        return $this->hasMany(MobilizationEmployee::class);
    }

    // Подразделения, где сотрудник является руководителем
    public function headedDepartments(): HasMany
    {
        return $this->hasMany(Department::class, 'head_employee_id');
    }
}