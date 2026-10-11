<?php

namespace Database\Seeders;

use App\Models\Notice;
use Database\Seeders\Concerns\ResolvesReferences;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Comunicados publicados e um rascunho.
 *
 * A data de publicação é contada para trás a partir de hoje: comunicado é
 * conteúdo datado, e um mural cujo item mais recente é de meses atrás não
 * demonstra o quadro de avisos, demonstra o abandono dele.
 */
class NoticeSeeder extends Seeder
{
    use ResolvesReferences;

    public function run(): void
    {
        $json = File::get(database_path('seeders/json/notices.json'));
        $data = json_decode($json);

        foreach ($data as $item) {
            $array = [
                'title' => $item->title,
                'content' => $item->content,
                'author_id' => $this->userId($item->author_email, 'notices.json'),
                'status' => $item->status,
                'published_at' => $item->published_days_ago === null
                    ? null
                    : now()->subDays($item->published_days_ago),
            ];

            Notice::create($array);
        }
    }
}
