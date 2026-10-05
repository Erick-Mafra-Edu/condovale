<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $json = File::get(database_path('seeders/json/roles.json'));
        $data = json_decode($json);

        foreach ($data as $item) {
            $array = [
                'name' => $item->name,
                'guard_name' => $item->guard_name,
            ];

            Role::create($array);
        }
    }
}
