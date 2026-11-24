<?php

namespace Tests\Feature;

use App\Models\CommonArea;
use App\Models\OccurrenceType;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\CommonAreaSeeder;
use Database\Seeders\OccurrenceTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * As tabelas de referência são configuração do produto: reaplicar a carga em
 * produção, para levar um caso de uso ou uma área nova, não pode duplicar o
 * que já existe nem desfazer o vínculo de ninguém.
 */
class ReferenceSeedersTest extends TestCase
{
    use RefreshDatabase;

    private const SEEDERS = [
        PermissionSeeder::class,
        RoleSeeder::class,
        RolePermissionSeeder::class,
        OccurrenceTypeSeeder::class,
        CommonAreaSeeder::class,
    ];

    /** @return array<string, int> */
    private function contar(): array
    {
        return [
            'permissions' => Permission::count(),
            'roles' => Role::count(),
            'role_has_permissions' => DB::table('role_has_permissions')->count(),
            'occurrence_types' => OccurrenceType::count(),
            'common_areas' => CommonArea::count(),
        ];
    }

    public function test_reaplicar_a_carga_de_referencia_nao_duplica_nada(): void
    {
        $this->seed(self::SEEDERS);
        $primeira = $this->contar();

        $this->seed(self::SEEDERS);

        $this->assertSame($primeira, $this->contar());
        $this->assertGreaterThan(0, $primeira['role_has_permissions']);
        $this->assertGreaterThan(0, $primeira['common_areas']);
    }

    public function test_reaplicar_devolve_o_que_o_json_diz(): void
    {
        $this->seed(self::SEEDERS);
        $area = CommonArea::orderBy('id')->firstOrFail();
        $area->update(['capacity' => 1]);

        $this->seed(CommonAreaSeeder::class);

        $this->assertNotSame(1, $area->refresh()->capacity);
    }
}
