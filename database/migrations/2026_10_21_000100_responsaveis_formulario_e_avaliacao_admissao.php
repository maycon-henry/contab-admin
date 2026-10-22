<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * regras.md RN-550 — quem salvou a última versão do formulário da admissão/funcionário;
 * RN-593 — quem do escritório avaliou (aceitou ou recusou) cada documento e quando.
 * Usuários nunca são excluídos (RN-233), por isso as chaves não têm cascade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->foreignId('formulario_salvo_por_id')->nullable()->constrained('users');
            $table->timestamp('formulario_salvo_em')->nullable();
        });

        Schema::table('documentos', function (Blueprint $table) {
            $table->foreignId('avaliado_por_id')->nullable()->constrained('users');
            $table->timestamp('avaliado_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('avaliado_por_id');
            $table->dropColumn('avaliado_em');
        });

        Schema::table('contratos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('formulario_salvo_por_id');
            $table->dropColumn('formulario_salvo_em');
        });
    }
};
