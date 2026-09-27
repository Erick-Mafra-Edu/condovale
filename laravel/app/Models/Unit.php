<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'block',
        'number',
        'code',
    ];

    public function occupancies(): HasMany
    {
        return $this->hasMany(UnitOccupancy::class);
    }

    public function residents(): HasMany
    {
        return $this->hasMany(User::class, 'unit_id');
    }
}
