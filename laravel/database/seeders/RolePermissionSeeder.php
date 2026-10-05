<?php

namespace Database\Seeders;

use App\Enums\UseCase;
use App\Enums\UserRole;
use App\Http\Actions\SyncUserRoleAction;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Materializa a matriz do diagrama de casos de uso nas tabelas do Spatie.
 *
 * A matriz em si continua em App\Enums\UserRole::useCases(), que espelha
 * frontend/app/domain/permissions.ts e é verificada pelo PermissionMatrixTest.
 * O que muda é quem responde em tempo de execução: a autorização passa a ser
 * lida do banco, e com isso a administração pode conceder um caso de uso a uma
 * pessoa específica sem depender de um deploy.
 *
 * Idempotente: pode rodar de novo em produção para aplicar uma mudança da
 * matriz. O `syncPermissions` de dentro da Action é o que faz um caso de uso
 * retirado da matriz sair também de quem já o tinha.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Todas as permissões existem, mesmo as que nenhum papel usa ainda:
        // uma permissão ausente faria a concessão individual falhar.
        foreach (UseCase::cases() as $useCase) {
            Permission::findOrCreate($useCase->value, 'web');
        }

        foreach (UserRole::cases() as $role) {
            SyncUserRoleAction::ensureRole($role);
        }
    }
}
