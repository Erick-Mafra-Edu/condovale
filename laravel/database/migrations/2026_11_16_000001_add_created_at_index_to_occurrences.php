<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RF06 / RNF01 — o relatório filtra sempre pelo período de abertura.
 *
 * O índice que já existia, `(status, created_at)`, só serve quando o status
 * vem junto. O relatório consulta o período inteiro, de todos os status, e
 * sem um índice que comece por `created_at` o banco lê a tabela toda a cada
 * relatório — o que vai ficando mais lento conforme o condomínio acumula anos
 * de chamados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('occurrences', function (Blueprint $table) {
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('occurrences', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });
    }
};
