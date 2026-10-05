<?php

namespace Tests\Unit;

use App\Enums\UseCase;
use App\Enums\UserRole;
use PHPUnit\Framework\TestCase;

/**
 * Trava os JSON da carga contra a matriz do diagrama de casos de uso.
 *
 * A autorização passou a ser lida do banco, e o banco vem destes arquivos.
 * Sem esta verificação a cadeia teria três cópias soltas — frontend, enum e
 * JSON — e a divergência só apareceria como um 403 que ninguém explica.
 *
 * O PermissionMatrixTest guarda a outra ponta: enum contra o frontend.
 */
class PermissionJsonMatrixTest extends TestCase
{
    private function json(string $arquivo): array
    {
        $caminho = __DIR__.'/../../database/seeders/json/'.$arquivo.'.json';

        $this->assertFileExists($caminho);

        return json_decode(file_get_contents($caminho));
    }

    public function test_permissions_json_tem_exatamente_os_casos_de_uso_do_enum(): void
    {
        $doJson = array_map(fn ($item) => $item->name, $this->json('permissions'));
        $doEnum = array_map(fn (UseCase $useCase) => $useCase->value, UseCase::cases());

        sort($doJson);
        sort($doEnum);

        $this->assertSame($doEnum, $doJson);
    }

    public function test_roles_json_tem_exatamente_os_perfis_do_enum(): void
    {
        $doJson = array_map(fn ($item) => $item->name, $this->json('roles'));
        $doEnum = array_map(fn (UserRole $role) => $role->value, UserRole::cases());

        sort($doJson);
        sort($doEnum);

        $this->assertSame($doEnum, $doJson);
    }

    public function test_role_has_permissions_json_reproduz_a_matriz(): void
    {
        $doJson = [];

        foreach ($this->json('role_has_permissions') as $item) {
            $doJson[$item->role][] = $item->permission;
        }

        foreach (UserRole::cases() as $role) {
            $esperado = array_map(fn (UseCase $useCase) => $useCase->value, $role->useCases());
            $gravado = $doJson[$role->value] ?? [];

            sort($esperado);
            sort($gravado);

            $this->assertSame(
                $esperado,
                $gravado,
                "role_has_permissions.json divergiu da matriz para o papel {$role->value}."
            );
        }
    }

    public function test_o_guard_de_todo_registro_e_o_web(): void
    {
        // Permissão criada noutro guard existe no banco mas nunca autoriza
        // nada, porque o guard de sessão da aplicação é o web.
        foreach (['permissions', 'roles'] as $arquivo) {
            foreach ($this->json($arquivo) as $item) {
                $this->assertSame('web', $item->guard_name, "{$arquivo}.json tem registro em outro guard.");
            }
        }
    }
}
