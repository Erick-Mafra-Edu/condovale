<?php

namespace App\Models;

use App\Enums\OccupantType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vínculo entre morador e unidade (associação Usuario-Unidade).
 *
 * A tabela guarda o histórico inteiro: encerrar um vínculo não apaga a linha,
 * apenas marca `is_active = false` e preenche `ended_at`. É isso que permite
 * saber quem morava na unidade na data de uma ocorrência antiga.
 */
class UnitOccupancy extends Model
{
    use HasFactory;

    protected $table = 'unit_occupancies';

    protected $fillable = [
        'unit_id',
        'user_id',
        'occupant_type',
        'started_at',
        'ended_at',
        'is_active',
    ];

    /** @var list<string> */
    protected $hidden = [];

    protected function casts(): array
    {
        return [
            'started_at' => 'date:Y-m-d',
            'ended_at' => 'date:Y-m-d',
            'is_active' => 'boolean',
            'occupant_type' => OccupantType::class,
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
