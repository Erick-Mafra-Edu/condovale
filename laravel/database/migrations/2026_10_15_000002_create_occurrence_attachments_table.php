<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RF04 — imagens anexadas ao chamado.
 *
 * O arquivo fica no disco privado `occurrence_attachments`; aqui fica o que o
 * liga ao chamado e o que é preciso para entregá-lo de volta. O nome com que
 * foi gravado (`path`) é aleatório e nunca o nome enviado: o nome original
 * pode trazer `../`, colidir com outro arquivo ou revelar coisa que o morador
 * não quis mostrar. Ele fica guardado só para exibição, em `original_name`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('occurrence_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('occurrence_id')->constrained('occurrences')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('path', 191);
            $table->string('original_name', 191);
            $table->string('mime_type', 50);
            $table->unsignedInteger('size');
            // Largura e altura de como ficou gravada, para a tela reservar o
            // espaço da imagem antes de ela carregar.
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->timestamps();

            $table->index(['occurrence_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('occurrence_attachments');
    }
};
