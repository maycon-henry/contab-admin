<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * regras.md RN-568 — documentos organizados para consulta: cada documento do funcionário
 * aponta para ele (juntando todos os contratos — RN-501) e guarda os dados do arquivo
 * (hash SHA-256 para integridade, formato e tamanho).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->foreignId('funcionario_id')->nullable()->constrained('funcionarios');
            $table->char('hash', 64)->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('tamanho')->nullable();

            $table->index(['funcionario_id', 'tipo']);
        });

        // documentos já existentes de admissão e desligamento ganham o funcionário do contrato
        DB::statement(<<<'SQL'
            UPDATE documentos d
               SET funcionario_id = c.funcionario_id
              FROM contratos c
             WHERE d.documentavel_type = 'App\Models\Contrato'
               AND d.documentavel_id = c.id
            SQL);
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropIndex(['funcionario_id', 'tipo']);
            $table->dropConstrainedForeignId('funcionario_id');
            $table->dropColumn(['hash', 'mime', 'tamanho']);
        });
    }
};
