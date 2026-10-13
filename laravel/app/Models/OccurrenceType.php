<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tipo de ocorrência — manutenção, estacionamento, barulho, e o que a
 * administração resolver acrescentar.
 *
 * É cadastro, e não enum, porque quem decide a lista é o condomínio: cada um
 * tem seus assuntos recorrentes, e acrescentar um tipo não pode depender de
 * deploy. O nome é a chave de negócio — é por ele que o seeder referencia o
 * tipo, e é por isso que ele é único.
 */
class OccurrenceType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'active',
    ];

    /**
     * Nada sensível: é uma tabela de referência. A declaração existe porque o
     * HandlePaginationAction só protege o que o model informar aqui.
     *
     * @var list<string>
     */
    protected $hidden = [];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(Occurrence::class);
    }

    /**
     * Um tipo já usado não pode ser apagado: os chamados que o referenciam
     * ficariam sem classificação. Quem sai de circulação é inativado.
     */
    public function isInUse(): bool
    {
        return $this->occurrences()->exists();
    }
}
