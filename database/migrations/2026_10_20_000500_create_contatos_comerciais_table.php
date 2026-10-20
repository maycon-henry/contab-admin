<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** regras.md RN-004 — pedidos de demonstração feitos pela página pública. */
    public function up(): void
    {
        Schema::create('contatos_comerciais', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('escritorio');
            $table->string('email');
            $table->string('telefone', 30)->nullable();
            $table->string('quantidade_empresas', 20)->nullable();
            $table->text('mensagem')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contatos_comerciais');
    }
};
