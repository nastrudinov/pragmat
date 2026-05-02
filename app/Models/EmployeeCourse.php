<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeCourse extends Model
{
    protected $table = 'employee_courses';
    
    protected $fillable = [
        'employee_id',
        'course_id',
        'status',
        'assigned_date',
        'completed_date',
        'expiration_date',
        'certificate_file_path',
        'certificate_number',      // Добавлено
        'regulatory_acts',          // Добавлено
        'last_reminder_sent'
    ];

    protected $casts = [
        'assigned_date' => 'datetime',
        'completed_date' => 'datetime',
        'expiration_date' => 'datetime',
        'last_reminder_sent' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
    
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}