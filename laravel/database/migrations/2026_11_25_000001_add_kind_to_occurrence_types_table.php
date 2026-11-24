<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UC7 — "Registrar denúncia". The nature of the type (App\Enums\OccurrenceKind)
 * tells a complaint from a maintenance request.
 *
 * Existing types become maintenance, the old behaviour: which ones are
 * complaints is a decision of the administration (PATCH /occurrence-types),
 * not something a migration should guess from a name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('occurrence_types', function (Blueprint $table) {
            $table->string('kind', 20)->default('maintenance')->after('description')->index();
        });
    }

    public function down(): void
    {
        Schema::table('occurrence_types', function (Blueprint $table) {
            $table->dropIndex(['kind']);
            $table->dropColumn('kind');
        });
    }
};
