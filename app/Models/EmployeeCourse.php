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
        'last_reminder_sent'
    ];

    protected $casts = [
        'assigned_date' => 'date',
        'completed_date' => 'date',
        'expiration_date' => 'date',
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