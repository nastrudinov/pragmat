<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthLog extends Model
{
    public $timestamps = false;
    
    protected $table = 'auth_logs';
    
    protected $fillable = [
        'user_id',
        'employee_id',
        'username',
        'event',
        'status',
        'ip_address',
        'user_agent',
        'device_type',
        'browser',
        'os',
        'message',
        'details',
        'created_at'
    ];
    
    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime'
    ];
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserAccount::class, 'user_id');
    }
    
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}