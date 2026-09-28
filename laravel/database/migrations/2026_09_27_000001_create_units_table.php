<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('block', 50)->nullable();
            $table->string('number', 50);
            $table->string('code', 100)->unique();
            // A unidade é inativada, nunca excluída: reservas e ocorrências
            // antigas continuam apontando para ela (Unidade.ativa no diagrama
            // de classes).
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });

        // A coluna users.unit_id nasceu sem chave estrangeira, porque a tabela
        // users é migrada antes de units e a restrição não teria a que se
        // referir. Ela é criada aqui, assim que units passa a existir.
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('unit_id')->references('id')->on('units')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
        });

        Schema::dropIfExists('units');
    }
};
