<?php

namespace App\Models;

use App\Enums\UseCase;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'unit_id',
        'avatar_url',
    ];

    /**
     * RNF02 — o hash da senha nunca sai da aplicação.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
        ];
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'resident_id');
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(Occurrence::class, 'resident_id');
    }

    public function assignedOccurrences(): HasMany
    {
        return $this->hasMany(Occurrence::class, 'assigned_employee_id');
    }

    public function unit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function unitOccupancies(): HasMany
    {
        return $this->hasMany(UnitOccupancy::class);
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /**
     * RN04 — a permissão depende do papel e de o usuário estar ativo.
     */
    public function hasUseCase(UseCase $useCase): bool
    {
        return $this->isActive() && $this->role->can($useCase);
    }
}
