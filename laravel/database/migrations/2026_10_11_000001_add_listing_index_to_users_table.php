<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UC-T01 / RNF01 — índice para a ordenação alfabética da listagem de moradores.
 *
 * As demais colunas que a listagem filtra já estavam indexadas (`role`,
 * `status`, `unit_id`), e a paginação passou a ordenar pela chave primária,
 * que é o índice agrupado. O nome ficava de fora: é por ele que a tela de
 * moradores ordena, e sem índice o banco precisa ler e ordenar a tabela
 * inteira para devolver dez linhas.
 *
 * A busca textual continua sem poder usar índice — `LIKE '%termo%'` com
 * curinga à esquerda nunca usa — e isso é aceito: a varredura é sobre uma
 * tabela de milhares de linhas, não de milhões.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['name']);
        });
    }
};
