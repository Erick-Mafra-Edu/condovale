<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Marca a conta criada pela administração, que nasce com uma senha
            // temporária. Enquanto estiver ligada, a sessão abre mas não
            // alcança nada além da troca de senha: é o que impede a senha
            // enviada por e-mail de virar a senha definitiva da pessoa.
            $table->boolean('must_change_password')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
    }
};
