<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logs', function (Blueprint $table) {
            $table->id();
            $table->integer('type_log_id')->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('description');
            $table->string('ip', 45)->nullable();
            $table->json('data_log')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['type_log_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logs');
    }
};
