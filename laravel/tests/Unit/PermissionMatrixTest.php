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
            'update-own-profile',
            'view-assigned-occurrences',
            'update-occurrence-progress',
            'finish-occurrence',
            'hand-over-occurrence',
        ]);
    }

    public function test_sindico_publica_comunicados_e_gera_relatorios(): void
    {
        $this->assertUseCases(UserRole::Syndic, [
            'login',
            'update-own-profile',
            'manage-units',
            'open-occurrence-for-resident',
            'manage-occurrence-types',
            'analyze-occurrences',
            'assign-occurrence',
            'close-any-occurrence',
            'publish-notices',
            'generate-reports',
        ]);
    }

    public function test_administrador_acumula_a_gestao_e_a_auditoria(): void
    {
        $this->assertUseCases(UserRole::Admin, [
            'login',
            'update-own-profile',
            'manage-units',
            'manage-residents',
            'link-residents-to-units',
            'open-occurrence-for-resident',
            'manage-occurrence-types',
            'analyze-occurrences',
            'assign-occurrence',
            'close-any-occurrence',
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

    public function test_o_cadastro_de_pessoas_e_so_do_administrador(): void
    {
        foreach ([UserRole::Resident, UserRole::Employee, UserRole::Syndic] as $role) {
            $this->assertFalse($role->can(UseCase::ManageResidents), "Papel {$role->value} não deveria ter manage-residents");
        }

        $this->assertTrue(UserRole::Admin->can(UseCase::ManageResidents));
    }

    public function test_encaminhar_e_da_administracao_e_do_sindico(): void
    {
        // A especificação dava o encaminhamento só ao administrador; o card de
        // encaminhamento o estendeu ao síndico. O funcionário não encaminha:
        // repassa o próprio chamado, por hand-over-occurrence.
        foreach ([UserRole::Admin, UserRole::Syndic] as $role) {
            $this->assertTrue($role->can(UseCase::AssignOccurrence), $role->value);
        }

        foreach ([UserRole::Resident, UserRole::Employee] as $role) {
            $this->assertFalse($role->can(UseCase::AssignOccurrence), $role->value);
        }

        $this->assertTrue(UserRole::Employee->can(UseCase::HandOverOccurrence));
    }

    public function test_rn03_as_unidades_sao_do_sindico_e_do_administrador(): void
    {
        // A nota do diagrama de sequência: "alterações estruturais de unidades
        // restritas ao Síndico/Administrador". É o único caso de uso
        // administrativo que o síndico divide — o cadastro de pessoas não.
        $this->assertTrue(UserRole::Syndic->can(UseCase::ManageUnits));
        $this->assertTrue(UserRole::Admin->can(UseCase::ManageUnits));

        $this->assertFalse(UserRole::Resident->can(UseCase::ManageUnits));
        $this->assertFalse(UserRole::Employee->can(UseCase::ManageUnits));
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
