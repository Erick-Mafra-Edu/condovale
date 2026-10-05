<?php

namespace Database\Seeders;

use App\Models\Notice;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class NoticeSeeder extends Seeder
{
    public function run(): void
    {
        $json = File::get(database_path('seeders/json/notices.json'));
        $data = json_decode($json);

        foreach ($data as $item) {
            $array = [
                'title' => $item->title,
                'content' => $item->content,
                'author_id' => $item->author_id,
                'status' => $item->status,
                'published_at' => $item->published_at,
            ];

            Notice::create($array);
        }
    }
}
