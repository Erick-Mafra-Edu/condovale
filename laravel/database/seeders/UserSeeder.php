<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

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
                'unit_id' => $item->unit_id,
                'avatar_url' => $item->avatar_url,
            ];

            User::create($array);
        }
    }
}
