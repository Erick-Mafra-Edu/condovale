<?php

namespace App\Http\Actions;

use App\Enums\OccurrenceHistoryType;
use App\Enums\OccurrenceStatus;
use App\Enums\TypeLogEnum;
use App\Enums\UseCase;
use App\Enums\UserRole;
use App\Exceptions\BusinessRuleException;
use App\Models\Occurrence;
use App\Models\User;

/**
 * RF04 / UC04 / RN05 — abertura de chamado.
 *
 * O chamado é sempre de um morador e nasce com status. Quem **abre** nem sempre
 * é quem **responde por ele**: o morador abre o próprio, e o síndico abre no
 * lugar de quem não conseguir — alguém sem acesso, sem o aplicativo, ou que
 * procurou a portaria. Nesse caso o chamado é do morador, e o histórico guarda
 * que foi o síndico que o registrou.
 *
 * Os dois papéis estão separados de propósito: `resident_id` identifica de quem
 * é o problema, e o autor do histórico identifica quem digitou. Misturá-los
 * faria o morador receber um chamado que não abriu sem rastro de quem abriu,
 * ou o síndico virar dono de um problema que não é dele.
 */
class CreateOccurrenceAction
{
    /**
     * @param  array{title: string, description: string, occurrence_type_id: int, resident_id?: int}  $attributes
     */
    public static function execute(User $author, array $attributes): Occurrence
    {
        $resident = self::resolveResident($author, $attributes);
        $emNomeDeOutro = $resident->id !== $author->id;

        $occurrence = Occurrence::create([
            'title' => $attributes['title'],
            'description' => $attributes['description'],
            'occurrence_type_id' => $attributes['occurrence_type_id'],
            'resident_id' => $resident->id,
            // A unidade acompanha o morador: é dela que o chamado parte, e é
            // por ela que a manutenção sabe onde ir.
            'unit_id' => $resident->unit_id,
            'status' => OccurrenceStatus::Open,
        ]);

        CreateOccurrenceHistoryAction::execute(
            $occurrence,
            $author,
            OccurrenceHistoryType::Created,
            null,
            OccurrenceStatus::Open,
            $emNomeDeOutro ? "Chamado registrado por {$author->name} a pedido do morador." : null,
        );

        $description = $emNomeDeOutro
            ? "Ocorrência {$occurrence->id} {$occurrence->title} aberta por {$author->name} para o morador {$resident->id} {$resident->name}."
            : "Ocorrência {$occurrence->id} {$occurrence->title} aberta pelo morador {$resident->id} {$resident->name}.";

        CreateLogAction::execute(TypeLogEnum::OCCURRENCE->value, $description, $occurrence);

        return $occurrence;
    }

    /**
     * De quem é o chamado: de quem o abriu, ou do morador informado.
     */
    private static function resolveResident(User $author, array $attributes): User
    {
        $informado = $attributes['resident_id'] ?? null;

        if ($informado === null || (int) $informado === $author->id) {
            if ($author->role !== UserRole::Resident) {
                // Síndico e funcionário respondem pelo condomínio, não moram
                // numa unidade: sem morador informado o chamado nasceria sem
                // de onde partiu nem a quem responder.
                throw BusinessRuleException::unprocessable(
                    'Informe o morador para quem o chamado está sendo aberto.'
                );
            }

            return $author;
        }

        if (! $author->hasUseCase(UseCase::OpenOccurrenceForResident)) {
            throw BusinessRuleException::forbidden(
                'Você só pode abrir chamados em seu próprio nome.'
            );
        }

        return self::checkResident(User::find($informado));
    }

    /**
     * O destinatário precisa ser um morador ativo e com unidade.
     *
     * Sem isto, o síndico abriria chamado no nome de um funcionário, de uma
     * conta desligada ou de alguém sem unidade — e o chamado ficaria sem
     * endereço, que é o que a manutenção usa para atender.
     */
    private static function checkResident(User $resident): User
    {
        if ($resident->role !== UserRole::Resident) {
            throw BusinessRuleException::unprocessable(
                "O usuário {$resident->id} {$resident->name} não é morador: o chamado precisa ser aberto para um morador."
            );
        }

        if (! $resident->isActive()) {
            throw BusinessRuleException::unprocessable(
                "O morador {$resident->id} {$resident->name} está inativo."
            );
        }

        if (! $resident->belongsToUnit()) {
            throw BusinessRuleException::unprocessable(
                "O morador {$resident->id} {$resident->name} não está vinculado a uma unidade."
            );
        }

        return $resident;
    }
}
