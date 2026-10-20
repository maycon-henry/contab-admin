<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // regras.md seção 10 — lotes de importação de XML
        Schema::create('lotes_importacao_xml', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escritorio_id')->constrained('escritorios');
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('enviado_por_id')->nullable()->constrained('users');
            $table->unsignedInteger('total_arquivos')->default(0);
            $table->unsignedInteger('importadas')->default(0);
            $table->unsignedInteger('duplicadas')->default(0);
            $table->unsignedInteger('rejeitadas')->default(0);
            $table->string('situacao', 20)->default('processando');
            $table->timestamps();
        });

        Schema::create('notas_fiscais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escritorio_id')->constrained('escritorios');
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->date('competencia');
            $table->string('chave_acesso', 44);
            $table->string('modelo', 2);
            $table->string('serie', 3);
            $table->string('numero', 9);
            $table->timestamp('data_emissao');
            $table->string('tipo_operacao', 1);
            $table->string('natureza_operacao')->nullable();
            $table->string('emitente_documento', 14);
            $table->string('emitente_nome');
            $table->string('emitente_uf', 2)->nullable();
            $table->string('destinatario_documento', 14)->nullable();
            $table->string('destinatario_nome')->nullable();
            $table->string('destinatario_uf', 2)->nullable();
            $table->json('cfops')->nullable();
            $table->decimal('valor_total', 15, 2);
            $table->decimal('valor_produtos', 15, 2)->nullable();
            $table->decimal('valor_icms', 15, 2)->nullable();
            $table->decimal('valor_ipi', 15, 2)->nullable();
            $table->string('protocolo', 20)->nullable();
            $table->timestamp('autorizada_em')->nullable();
            $table->string('classificacao', 30);
            $table->string('situacao', 20)->default('autorizada');
            $table->timestamp('cancelada_em')->nullable();
            $table->string('protocolo_cancelamento', 20)->nullable();
            $table->string('arquivo');
            $table->string('hash_sha256', 64);
            $table->string('nome_original');
            $table->foreignId('lote_id')->nullable()->constrained('lotes_importacao_xml');
            $table->foreignId('enviado_por_id')->nullable()->constrained('users');
            $table->date('competencia_original')->nullable();
            $table->string('justificativa_mudanca')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'chave_acesso']);
            $table->index(['empresa_id', 'competencia', 'classificacao']);
        });

        Schema::create('arquivos_importacao_xml', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lote_id')->constrained('lotes_importacao_xml')->cascadeOnDelete();
            $table->string('nome_original');
            $table->string('arquivo');
            $table->string('resultado', 20)->default('pendente');
            $table->string('motivo')->nullable();
            $table->foreignId('nota_fiscal_id')->nullable()->constrained('notas_fiscais');
            $table->date('competencia')->nullable();
            $table->timestamps();
        });

        // RN-731 cancelamento recebido antes da nota
        Schema::create('cancelamentos_pendentes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->string('chave_acesso', 44);
            $table->string('protocolo', 20)->nullable();
            $table->timestamp('cancelada_em')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'chave_acesso']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cancelamentos_pendentes');
        Schema::dropIfExists('arquivos_importacao_xml');
        Schema::dropIfExists('notas_fiscais');
        Schema::dropIfExists('lotes_importacao_xml');
    }
};
