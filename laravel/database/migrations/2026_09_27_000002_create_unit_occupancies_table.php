<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_occupancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            // Minúsculas, como todos os demais enums do projeto (App\Enums\OccupantType).
            $table->string('occupant_type', 30)->default('tenant');
            $table->date('started_at');
            $table->date('ended_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['unit_id', 'is_active']);
            $table->index(['user_id', 'is_active']);

            // Um morador pode ocupar a mesma unidade mais de uma vez ao longo
            // do tempo, então a unicidade não pode ser só (unit_id, user_id).
            // O índice abaixo acelera a checagem de vínculo ativo duplicado que
            // a LinkResidentToUnitAction faz; a regra em si mora na Action,
            // porque índice parcial não é portável entre SQLite e MySQL.
            $table->index(['unit_id', 'user_id', 'is_active'], 'unit_occupancies_link_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_occupancies');
    }
};
