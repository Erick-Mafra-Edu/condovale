<?php

namespace App\Models;

use App\Enums\UseCase;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

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

    /**
     * Unidade vigente do morador.
     *
     * É um atalho denormalizado: a fonte de verdade do vínculo é
     * unit_occupancies, e esta coluna guarda a ocupação ativa mais recente
     * para que listagens e o payload do usuário não precisem de junção. Quem
     * mantém as duas em acordo é a LinkResidentToUnitAction, dentro da mesma
     * transação — nenhum outro ponto do sistema deve escrever em unit_id.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function unitOccupancies(): HasMany
    {
        return $this->hasMany(UnitOccupancy::class);
    }

    public function activeUnitOccupancies(): HasMany
    {
        return $this->unitOccupancies()->where('is_active', true);
    }

    /**
     * O morador responde por uma unidade; os demais papéis respondem pelo
     * condomínio inteiro e por isso não precisam de vínculo.
     */
    public function requiresUnitLink(): bool
    {
        return $this->role === UserRole::Resident;
    }

    public function belongsToUnit(): bool
    {
        return $this->activeUnitOccupancies()->exists();
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /**
     * RN04 — a permissão depende da autorização e de o usuário estar ativo.
     *
     * Quem responde é o Spatie, lendo as tabelas de papéis e permissões: assim
     * a administração pode conceder um caso de uso a uma pessoa específica sem
     * depender de um deploy. O `can()` devolve falso para permissão que não
     * existe, em vez de lançar — uma permissão ainda não semeada se comporta
     * como ausente, e a rota responde 403.
     */
    public function hasUseCase(UseCase $useCase): bool
    {
        return $this->isActive() && $this->can($useCase->value);
    }
}
