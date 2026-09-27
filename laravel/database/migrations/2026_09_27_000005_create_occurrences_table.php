<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('occurrences', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->string('category', 50)->default('geral');
            $table->foreignId('resident_id')->constrained('users')->onDelete('cascade');
            $table->unsignedBigInteger('unit_id')->nullable()->index();
            $table->foreignId('assigned_employee_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('status', 30)->default('open')->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['resident_id', 'status']);
            $table->index(['assigned_employee_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('occurrences');
    }
};
