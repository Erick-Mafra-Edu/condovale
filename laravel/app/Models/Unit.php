<?php

namespace App\Models;

use App\Enums\UnitStatus;
use Illuminate\Database\Eloquent\Builder;
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
        'status',
    ];

    /**
     * Nada a esconder aqui: a unidade não guarda dado pessoal. A declaração
     * existe porque o HandlePaginationAction só protege o que o model declara,
     * e um model sem $hidden abre todas as colunas à query string.
     *
     * @var list<string>
     */
    protected $hidden = [];

    protected function casts(): array
    {
        return [
            'status' => UnitStatus::class,
        ];
    }

    public function occupancies(): HasMany
    {
        return $this->hasMany(UnitOccupancy::class);
    }

    /**
     * Ocupações vigentes — é por aqui que se sabe quem mora na unidade hoje.
     */
    public function activeOccupancies(): HasMany
    {
        return $this->occupancies()->where('is_active', true);
    }

    public function residents(): HasMany
    {
        return $this->hasMany(User::class, 'unit_id');
    }

    public function isActive(): bool
    {
        return $this->status === UnitStatus::Active;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', UnitStatus::Active);
    }
}
