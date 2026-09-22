<?php

namespace Tests\Unit;

use App\Enums\UseCase;
use App\Enums\UserRole;
use PHPUnit\Framework\TestCase;

/**
 * Matriz de permissões do diagrama de casos de uso (documento M1, seção 8.1).
 *
 * É a mesma matriz de frontend/app/domain/permissions.ts: se as duas
 * divergirem, a interface mostra ações que o backend recusa.
 */
class PermissionMatrixTest extends TestCase
{
    public function test_morador_possui_apenas_os_casos_de_uso_do_diagrama(): void
    {
        $this->assertUseCases(UserRole::Resident, [
            'login',
            'update-own-profile',
            'view-notices',
            'create-occurrence',
            'track-own-occurrences',
            'view-common-areas',
            'request-reservation',
            'view-own-reservations',
            'cancel-own-reservation',
        ]);
    }

    public function test_funcionario_possui_apenas_o_atendimento_atribuido(): void
    {
        $this->assertUseCases(UserRole::Employee, [
            'login',
            'view-assigned-occurrences',
            'update-occurrence-progress',
            'finish-occurrence',
        ]);
    }

    public function test_sindico_publica_comunicados_e_gera_relatorios(): void
    {
        $this->assertUseCases(UserRole::Syndic, [
            'login',
            'publish-notices',
            'generate-reports',
        ]);
    }

    public function test_administrador_acumula_a_gestao_e_a_auditoria(): void
    {
        $this->assertUseCases(UserRole::Admin, [
            'login',
            'manage-units',
            'manage-residents',
            'link-residents-to-units',
            'analyze-occurrences',
            'assign-occurrence',
            'publish-notices',
            'manage-reservations',
            'approve-or-reject-reservation',
            'generate-reports',
            'view-audit-reports',
        ]);
    }

    public function test_auditoria_e_exclusiva_do_administrador(): void
    {
        foreach (UserRole::cases() as $role) {
            $this->assertSame(
                $role === UserRole::Admin,
                $role->can(UseCase::ViewAuditReports),
                "Papel {$role->value} não deveria consultar auditoria",
            );
        }
    }

    public function test_nenhum_papel_alem_do_administrador_gerencia_cadastros(): void
    {
        foreach ([UseCase::ManageResidents, UseCase::ManageUnits, UseCase::AssignOccurrence] as $useCase) {
            foreach ([UserRole::Resident, UserRole::Employee, UserRole::Syndic] as $role) {
                $this->assertFalse($role->can($useCase), "Papel {$role->value} não deveria ter {$useCase->value}");
            }

            $this->assertTrue(UserRole::Admin->can($useCase));
        }
    }

    /**
     * @param  list<string>  $expected
     */
    private function assertUseCases(UserRole $role, array $expected): void
    {
        $actual = array_map(static fn (UseCase $useCase) => $useCase->value, $role->useCases());

        sort($actual);
        sort($expected);

        $this->assertSame($expected, $actual);
    }
}
