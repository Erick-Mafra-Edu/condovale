<?php

namespace Database\Seeders;

use App\Enums\NoticeStatus;
use App\Models\Notice;
use App\Models\User;
use Illuminate\Database\Seeder;

class NoticeSeeder extends Seeder
{
    public function run(): void
    {
        $sindico = User::where('email', 'sindico@condovale.com')->first();

        if ($sindico) {
            Notice::firstOrCreate(
                ['title' => 'Manutenção Preventiva dos Elevadores'],
                [
                    'content' => 'Informamos que no dia 30/09 os elevadores do Bloco A passarão por manutenção preventiva das 08h às 12h.',
                    'author_id' => $sindico->id,
                    'status' => NoticeStatus::Published,
                    'published_at' => now(),
                ]
            );

            Notice::firstOrCreate(
                ['title' => 'Assembleia Geral Ordinária de Condôminos'],
                [
                    'content' => 'Convocamos todos os condôminos para a Assembleia Geral no próximo dia 15 às 19h30 no Salão de Festas Principal.',
                    'author_id' => $sindico->id,
                    'status' => NoticeStatus::Published,
                    'published_at' => now()->subDays(2),
                ]
            );
        }
    }
}
