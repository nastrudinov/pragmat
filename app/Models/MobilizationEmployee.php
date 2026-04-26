<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobilizationEmployee extends Model
{
    protected $table = 'mobilization_employees';
    
    public $timestamps = false;
    
    protected $fillable = [
        'mobilization_id',
        'employee_id',
        'role',
        'assigned_at'
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function mobilization(): BelongsTo
    {
        return $this->belongsTo(Mobilization::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}