<?php

namespace Database\Seeders\Concerns;

use App\Models\CommonArea;
use App\Models\Occurrence;
use App\Models\Unit;
use App\Models\User;
use RuntimeException;

/**
 * Resolve a chave estrangeira pela chave de negócio, e não pelo id.
 *
 * Os JSON da carga apontavam uns para os outros por número — `resident_id: 5`
 * dependia de o morador ser a quinta linha de users.json. Bastava alguém
 * inserir um registro no meio do arquivo para a ocorrência trocar de dono em
 * silêncio, e nada acusaria: o id continuaria existindo, apontando para outra
 * pessoa.
 *
 * Com o e-mail e o código no lugar do número, o arquivo passa a dizer o que
 * representa ("resident@gmail.com reservou o Salão"), a ordem das linhas deixa
 * de ser contrato, e uma referência quebrada estoura nomeando os dois lados em
 * vez de gravar a linha errada.
 */
trait ResolvesReferences
{
    protected function userId(string $email, string $origem): int
    {
        return User::where('email', $email)->value('id')
            ?? throw new RuntimeException("{$origem} cita o usuário {$email}, que não está em users.json.");
    }

    protected function unitId(string $code, string $origem): int
    {
        return Unit::where('code', $code)->value('id')
            ?? throw new RuntimeException("{$origem} cita a unidade {$code}, que não está em units.json.");
    }

    protected function commonAreaId(string $name, string $origem): int
    {
        return CommonArea::where('name', $name)->value('id')
            ?? throw new RuntimeException("{$origem} cita a área comum {$name}, que não está em common_areas.json.");
    }

    protected function occurrenceId(string $title, string $origem): int
    {
        return Occurrence::where('title', $title)->value('id')
            ?? throw new RuntimeException("{$origem} cita a ocorrência \"{$title}\", que não está em occurrences.json.");
    }

    /**
     * A mesma resolução, para colunas que aceitam nulo.
     */
    protected function optionalUserId(?string $email, string $origem): ?int
    {
        return $email === null ? null : $this->userId($email, $origem);
    }
}
