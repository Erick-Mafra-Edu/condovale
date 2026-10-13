<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RF04 — o tipo da ocorrência vira cadastro, no lugar de texto livre.
 *
 * A coluna `category` era uma string digitada a cada chamado. Com ela, "Barulho",
 * "barulho" e "Perturbação do sossego" viram três categorias distintas, e o
 * relatório do síndico passa a contar errado justamente o que ele precisa
 * medir. Também não havia como a administração acrescentar um tipo novo sem
 * deploy.
 *
 * A chave estrangeira é `restrictOnDelete` de propósito: apagar um tipo em uso
 * deixaria chamados antigos sem classificação, e é o banco que garante isso
 * mesmo se alguém apagar por fora da aplicação.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('occurrence_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('description', 255)->nullable();
            // Tipo em uso não pode ser apagado, então precisa de uma forma de
            // sair de circulação sem sumir dos chamados que já o referenciam.
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('occurrences', function (Blueprint $table) {
            $table->foreignId('occurrence_type_id')
                ->nullable()
                ->after('description')
                ->constrained('occurrence_types')
                ->restrictOnDelete();

            $table->index(['occurrence_type_id', 'status']);
        });

        // Sai depois da coluna nova entrar: os dados de exemplo são recriados
        // pela carga, e manter as duas conviveria com duas fontes de verdade
        // para a mesma informação.
        Schema::table('occurrences', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }

    public function down(): void
    {
        Schema::table('occurrences', function (Blueprint $table) {
            $table->string('category', 50)->default('geral')->after('description');
        });

        Schema::table('occurrences', function (Blueprint $table) {
            $table->dropIndex(['occurrence_type_id', 'status']);
            $table->dropConstrainedForeignId('occurrence_type_id');
        });

        Schema::dropIfExists('occurrence_types');
    }
};
