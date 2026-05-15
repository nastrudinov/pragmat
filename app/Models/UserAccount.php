<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Hash;

class UserAccount extends Authenticatable  // Изменено с Model на Authenticatable
{
    use HasApiTokens;  // Добавлено для Sanctum

    protected $table = 'user_accounts';
    
    protected $fillable = [
        'employee_id',
        'username',
        'password_hash',
        'role',
        'status',
        'last_login'
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'last_login' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Sanctum использует поле password для аутентификации
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function verifyPassword($password): bool
    {
        return Hash::check($password, $this->password_hash);
    }
}
