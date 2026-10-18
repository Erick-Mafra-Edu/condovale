<?php

namespace App\Http\Actions;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class RunFreshMigrationAction
{
    /**
     * Drops every table, runs all migrations again and loads the seed data.
     */
    public static function execute(): string
    {
        // Shared hosting caps execution time low and a fresh migration with
        // seeds easily crosses 30s. The raise is best effort: the host may
        // refuse it, and then the request dies halfway through the schema.
        @set_time_limit((int) config('deploy.timeout'));

        Artisan::call('migrate:fresh', [
            // Without --force the command refuses to run outside development.
            '--force' => true,
            '--seed' => true,
        ]);

        $saida = trim(Artisan::output());
        $removidos = self::dropAttachments();

        return $saida."\n\nAnexos de ocorrência removidos do disco: {$removidos}.";
    }

    /**
     * Aplica só as migrations que ainda não rodaram, sem apagar nada.
     *
     * Recriar era a única opção, e ela apaga o que o time cadastrou testando
     * a interface desde a última carga. Quando a mudança é só uma tabela
     * nova, não há motivo para levar o resto junto.
     */
    public static function pending(): string
    {
        @set_time_limit((int) config('deploy.timeout'));

        Artisan::call('migrate', ['--force' => true]);

        return trim(Artisan::output());
    }

    /**
     * Os anexos pertencem às linhas que o migrate:fresh acabou de apagar.
     *
     * Sem esta limpeza, cada recarga do banco em produção deixaria as fotos
     * antigas no disco sem registro que aponte para elas — invisíveis para a
     * aplicação e ocupando inodes de uma conta que tem por volta de 30 mil. A
     * conta para de aceitar arquivo novo, de qualquer tipo, quando chega ao
     * teto.
     */
    private static function dropAttachments(): int
    {
        $disco = Storage::disk(config('uploads.occurrence_attachments.disk'));

        if (! is_dir($disco->path(''))) {
            return 0;
        }

        $arquivos = $disco->allFiles();

        foreach ($disco->directories() as $pasta) {
            $disco->deleteDirectory($pasta);
        }

        $disco->delete($disco->files());

        return count($arquivos);
    }
}
