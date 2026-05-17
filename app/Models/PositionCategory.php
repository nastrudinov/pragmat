<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PositionCategory extends Model
{
    protected $fillable = [
        'name'
    ];

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class, 'category_id');
    }
}