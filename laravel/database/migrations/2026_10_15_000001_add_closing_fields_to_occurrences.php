<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RF04 / RN06 — o encerramento do chamado e quem pode fazê-lo.
 *
 * `occurrence_types.employee_can_close` decide, por tipo, se o funcionário
 * encerra o próprio atendimento ou se isso fica com o síndico e a
 * administração. Um vazamento o funcionário conserta e dá baixa; uma briga por
 * vaga ou barulho entre vizinhos não se resolve com ferramenta, e quem encerra
 * é quem responde pelo condomínio. O padrão é `false`: tipo novo que ninguém
 * configurou não deve deixar o funcionário encerrar chamado de conflito.
 *
 * `occurrences.resolved` e `conclusion` registram o desfecho. Encerrar não é o
 * mesmo que resolver — a peça pode não existir, o problema pode ser da
 * concessionária —, e o relatório do síndico precisa distinguir o que foi
 * resolvido do que só foi fechado. Ficam nulos enquanto o chamado está aberto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('occurrence_types', function (Blueprint $table) {
            $table->boolean('employee_can_close')->default(false)->after('description');
        });

        Schema::table('occurrences', function (Blueprint $table) {
            $table->boolean('resolved')->nullable()->after('status');
            $table->text('conclusion')->nullable()->after('resolved');
        });
    }

    public function down(): void
    {
        Schema::table('occurrences', function (Blueprint $table) {
            $table->dropColumn(['resolved', 'conclusion']);
        });

        Schema::table('occurrence_types', function (Blueprint $table) {
            $table->dropColumn('employee_can_close');
        });
    }
};
