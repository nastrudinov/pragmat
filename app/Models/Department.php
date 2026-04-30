<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = [
        'name',
        'code',
        'parent_id',
        'head_employee_id',
        'phone',
        'email',
        'description',
        'sort_order',
        'status'
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Родительское подразделение
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    // Дочерние подразделения
    public function children(): HasMany
    {
        return $this->hasMany(Department::class, 'parent_id');
    }

    // Руководитель подразделения
    public function head(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'head_employee_id');
    }

    // Сотрудники подразделения
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    // Рекурсивное получение всех сотрудников (включая дочерние подразделения)
    public function getAllEmployees()
    {
        $employeeIds = $this->employees->pluck('id')->toArray();
        
        foreach ($this->children as $child) {
            $employeeIds = array_merge($employeeIds, $child->getAllEmployees()->pluck('id')->toArray());
        }
        
        return Employee::whereIn('id', $employeeIds)->get();
    }
}