<?php

namespace App\Http\Requests;

use App\Enums\AuditAction;
use App\Enums\AuditEntity;
use App\Enums\UserRole;

/**
 * RN16 — a consulta do relatório de auditoria: período, entidade, registro,
 * responsável, perfil e ação. Período, página e exportação vêm de
 * ReportRequest, como nos relatórios operacionais.
 *
 * O registro (`entity_id`) só faz sentido com a entidade: o 3 de uma reserva
 * não é o 3 de uma ocorrência.
 */
class AuditLogRequest extends ReportRequest
{
    public function rules(): array
    {
        $valores = fn (array $cases) => implode(',', array_map(fn ($case) => $case->value, $cases));

        return parent::rules() + [
            'entity' => 'nullable|required_with:entity_id|in:'.$valores(AuditEntity::cases()),
            'entity_id' => 'nullable|integer|min:1',
            'user_id' => 'nullable|integer|exists:users,id',
            'role' => 'nullable|in:'.$valores(UserRole::cases()),
            'action' => 'nullable|in:'.$valores(AuditAction::cases()),
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'entity.required_with' => 'Informe a entidade do registro procurado.',
            'entity.in' => 'A entidade informada não existe.',
            'entity_id.integer' => 'O registro deve ser informado pelo código.',
            'entity_id.min' => 'O registro deve ser informado pelo código.',
            'user_id.integer' => 'O responsável deve ser informado pelo código.',
            'user_id.exists' => 'O responsável informado não existe.',
            'role.in' => 'O perfil informado não existe.',
            'action.in' => 'A ação informada não existe.',
        ];
    }
}
