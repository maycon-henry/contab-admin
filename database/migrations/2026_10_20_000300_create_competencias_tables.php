<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // regras.md seção 9.2 — catálogo de documentos (por escritório)
        Schema::create('tipos_documento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escritorio_id')->constrained('escritorios');
            $table->string('nome');
            $table->text('instrucoes')->nullable();
            $table->string('tipo', 20)->default('documento');
            $table->json('formatos')->nullable();
            $table->boolean('multiplos_arquivos')->default(false);
            $table->unsignedTinyInteger('prazo_dia')->default(10);
            $table->boolean('obrigatorio')->default(true);
            $table->boolean('exige_validade')->default(false);
            $table->boolean('eh_certidao')->default(false);
            $table->boolean('aproveitar_valido')->default(false);
            $table->string('aplicacao', 20)->default('todas');
            $table->json('regimes')->nullable();
            $table->string('chave_sistema', 40)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        // aplicação "empresas selecionadas"
        Schema::create('empresa_tipo_documento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_documento_id')->constrained('tipos_documento')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->timestamps();

            $table->unique(['tipo_documento_id', 'empresa_id']);
        });

        // RN-616 ajustes da rotina por empresa
        Schema::create('empresa_rotina_ajustes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('tipo_documento_id')->constrained('tipos_documento');
            $table->boolean('dispensado')->default(false);
            $table->string('justificativa')->nullable();
            $table->unsignedTinyInteger('prazo_dia')->nullable();
            $table->boolean('obrigatorio')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'tipo_documento_id']);
        });

        // regras.md seção 9 — competências
        Schema::create('competencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escritorio_id')->constrained('escritorios');
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->date('competencia');
            $table->string('situacao', 20)->default('aberta');
            $table->foreignId('fechada_por_id')->nullable()->constrained('users');
            $table->timestamp('fechada_em')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'competencia']);
            $table->index(['escritorio_id', 'competencia']);
        });

        // regras.md seção 9.4 — itens da competência (cópia das configurações do tipo)
        Schema::create('itens_competencia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escritorio_id')->constrained('escritorios');
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('competencia_id')->constrained('competencias');
            $table->foreignId('tipo_documento_id')->constrained('tipos_documento');
            $table->string('nome');
            $table->string('tipo', 20);
            $table->date('prazo');
            $table->boolean('obrigatorio')->default(true);
            $table->boolean('exige_validade')->default(false);
            $table->boolean('eh_certidao')->default(false);
            $table->string('situacao', 20)->default('pendente');
            $table->boolean('sem_movimento')->default(false);
            $table->text('observacao_empresa')->nullable();
            $table->date('validade')->nullable();
            $table->string('resultado_certidao', 40)->nullable();
            $table->string('motivo_recusa', 40)->nullable();
            $table->text('complemento_recusa')->nullable();
            $table->boolean('aceito_por_validade')->default(false);
            $table->foreignId('documento_aproveitado_id')->nullable();
            $table->foreignId('enviado_por_id')->nullable()->constrained('users');
            $table->timestamp('enviado_em')->nullable();
            $table->foreignId('avaliado_por_id')->nullable()->constrained('users');
            $table->timestamp('avaliado_em')->nullable();
            $table->unsignedSmallInteger('vezes_cobrado')->default(0);
            $table->timestamps();

            $table->unique(['competencia_id', 'tipo_documento_id']);
            $table->index(['escritorio_id', 'situacao', 'prazo']);
        });

        // RN-655 histórico de avaliações (nunca atualizado)
        Schema::create('avaliacoes_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_competencia_id')->constrained('itens_competencia');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('resultado', 20);
            $table->string('motivo_recusa', 40)->nullable();
            $table->text('complemento')->nullable();
            $table->date('validade')->nullable();
            $table->string('resultado_certidao', 40)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // Documentos (polimórfico): itens da competência, contratos, empresas
        Schema::create('documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escritorio_id')->constrained('escritorios');
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->morphs('documentavel');
            $table->string('tipo', 60)->nullable();
            $table->string('arquivo');
            $table->string('nome_original');
            $table->string('origem', 20);
            $table->boolean('visivel_empresa')->default(true);
            $table->date('validade')->nullable();
            // avaliação de documento de admissão (RN-593)
            $table->string('avaliacao', 20)->nullable();
            $table->text('motivo_recusa')->nullable();
            $table->boolean('substituido')->default(false);
            $table->foreignId('enviado_por_id')->nullable()->constrained('users');
            $table->timestamps();
        });

        // regras.md seção 16.1 — histórico da cobrança
        Schema::create('cobrancas_documentacao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escritorio_id')->constrained('escritorios');
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->date('data_referencia');
            $table->string('tipo', 20);
            $table->date('competencia_referencia');
            $table->json('destinatarios');
            $table->json('itens');
            $table->string('resultado', 40);
            $table->foreignId('enviado_por_id')->nullable()->constrained('users');
            $table->timestamps();

            // RN-1320: nunca duas cobranças automáticas no mesmo dia
            $table->unique(['empresa_id', 'data_referencia', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobrancas_documentacao');
        Schema::dropIfExists('documentos');
        Schema::dropIfExists('avaliacoes_item');
        Schema::dropIfExists('itens_competencia');
        Schema::dropIfExists('competencias');
        Schema::dropIfExists('empresa_rotina_ajustes');
        Schema::dropIfExists('empresa_tipo_documento');
        Schema::dropIfExists('tipos_documento');
    }
};
