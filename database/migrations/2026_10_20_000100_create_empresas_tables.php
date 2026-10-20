<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // regras.md seção 7 — empresas clientes
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escritorio_id')->constrained('escritorios');
            $table->string('cnpj', 14);
            $table->string('razao_social');
            $table->string('nome_fantasia')->nullable();
            $table->string('natureza_juridica')->nullable();
            $table->string('regime_tributario', 30);
            $table->date('data_inicio_atendimento');
            $table->string('situacao', 20)->default('ativa');
            $table->date('data_abertura')->nullable();
            $table->string('porte', 20)->nullable();
            $table->string('cnae_principal', 10)->nullable();
            $table->decimal('capital_social', 15, 2)->nullable();
            $table->string('inscricao_estadual')->nullable();
            $table->string('inscricao_municipal')->nullable();
            // endereço da sede
            $table->string('cep', 8)->nullable();
            $table->string('logradouro')->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('complemento')->nullable();
            $table->string('bairro')->nullable();
            $table->string('municipio')->nullable();
            $table->string('uf', 2)->nullable();
            $table->string('telefone', 20)->nullable();
            $table->string('email')->nullable();
            // RN-407 contato responsável
            $table->string('contato_nome')->nullable();
            $table->string('contato_cpf', 11)->nullable();
            $table->string('contato_telefone', 20)->nullable();
            $table->string('contato_email')->nullable();
            // RN-410 dados de empregador
            $table->string('classificacao_tributaria', 2)->nullable();
            $table->boolean('indicativo_cooperativa')->default(false);
            $table->boolean('indicativo_construtora')->default(false);
            $table->boolean('indicativo_desoneracao')->default(false);
            // RN-1311 / RN-1317 cobrança
            $table->unsignedTinyInteger('dia_corte_cobranca')->nullable();
            $table->date('cobranca_suspensa_ate')->nullable();
            $table->string('cobranca_suspensa_motivo')->nullable();
            $table->timestamps();

            $table->unique(['escritorio_id', 'cnpj']);
            $table->index(['escritorio_id', 'situacao']);
        });

        // RN-403 histórico de regime tributário
        Schema::create('empresa_regimes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->string('regime_tributario', 30);
            $table->date('vigencia_inicio');
            $table->date('vigencia_fim')->nullable();
            $table->timestamps();
        });

        // RN-404 quadro societário
        Schema::create('socios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->string('nome');
            $table->string('documento', 14);
            $table->string('qualificacao')->nullable();
            $table->decimal('percentual', 5, 2);
            $table->date('data_entrada')->nullable();
            $table->timestamps();
        });

        // RN-411 estabelecimentos
        Schema::create('estabelecimentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->string('cnpj', 14);
            $table->boolean('matriz')->default(false);
            $table->string('nome')->nullable();
            $table->string('municipio')->nullable();
            $table->string('uf', 2)->nullable();
            $table->string('cnae_preponderante', 10)->nullable();
            $table->decimal('aliquota_rat', 3, 1)->nullable();
            $table->decimal('fap', 5, 4)->nullable();
            $table->unsignedTinyInteger('grau_risco')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'cnpj']);
        });

        // RN-240 vínculo usuário da empresa x empresas
        Schema::create('empresa_usuario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();

            $table->unique(['empresa_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_usuario');
        Schema::dropIfExists('estabelecimentos');
        Schema::dropIfExists('socios');
        Schema::dropIfExists('empresa_regimes');
        Schema::dropIfExists('empresas');
    }
};
