<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * As contas da demonstração — uma por perfil, mais as variações de estado.
 *
 * A senha vai em claro no JSON e o cast `hashed` do model a transforma em
 * Argon2id na gravação: em lugar nenhum da carga existe hash escrito à mão.
 *
 * A coluna `unit_id` não é preenchida aqui de propósito. Ela é um atalho para
 * a ocupação vigente, e quem a mantém é o UnitOccupancySeeder — do mesmo modo
 * que, em produção, quem a mantém é a LinkResidentToUnitAction. Preenchê-la
 * nos dois lugares permitiria a carga nascer dizendo que a pessoa mora numa
 * unidade sem haver vínculo que o sustente.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $json = File::get(database_path('seeders/json/users.json'));
        $data = json_decode($json);

        foreach ($data as $item) {
            $array = [
                'name' => $item->name,
                'email' => $item->email,
                'password' => $item->password,
                'role' => $item->role,
                'status' => $item->status,
                'avatar_url' => $item->avatar_url,
                'must_change_password' => $item->must_change_password,
            ];

            User::create($array);
        }
    }
}
