<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** regras.md seção 19 — despesas da empresa cliente. */
    public function up(): void
    {
        Schema::create('despesas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escritorio_id')->constrained('escritorios');
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->string('descricao');
            $table->decimal('valor', 15, 2);
            $table->date('data_vencimento');
            $table->foreignId('cadastrada_por_id')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'data_vencimento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('despesas');
    }
};
