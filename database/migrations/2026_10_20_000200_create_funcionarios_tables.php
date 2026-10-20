<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // regras.md seção 8.2 — dados pessoais
        Schema::create('funcionarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escritorio_id')->constrained('escritorios');
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->string('nome');
            $table->string('nome_social')->nullable();
            $table->string('cpf', 11);
            $table->date('data_nascimento')->nullable();
            $table->string('sexo', 1)->nullable();
            $table->string('raca_cor', 20)->nullable();
            $table->string('estado_civil', 20)->nullable();
            $table->string('grau_instrucao', 40)->nullable();
            $table->string('pais_nascimento')->nullable();
            $table->string('nacionalidade')->nullable();
            $table->string('cep', 8)->nullable();
            $table->string('logradouro')->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('complemento')->nullable();
            $table->string('bairro')->nullable();
            $table->string('municipio')->nullable();
            $table->string('uf', 2)->nullable();
            $table->string('telefone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('rg')->nullable();
            $table->string('pis', 11)->nullable();
            // RN-513 — criptografados (cast encrypted)
            $table->text('banco')->nullable();
            $table->text('agencia')->nullable();
            $table->text('conta')->nullable();
            $table->text('chave_pix')->nullable();
            // RN-514 / RN-515
            $table->boolean('pessoa_com_deficiencia')->default(false);
            $table->string('tipo_deficiencia')->nullable();
            $table->boolean('estrangeiro')->default(false);
            $table->string('condicao_ingresso')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'cpf']);
        });

        // RN-512
        Schema::create('dependentes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funcionario_id')->constrained('funcionarios')->cascadeOnDelete();
            $table->string('nome');
            $table->string('cpf', 11)->nullable();
            $table->date('data_nascimento');
            $table->string('tipo', 30);
            $table->boolean('dependente_irrf')->default(false);
            $table->boolean('dependente_salario_familia')->default(false);
            $table->timestamps();
        });

        // regras.md seções 8.3 a 8.5 e 8.8 — contrato de trabalho (admissão e desligamento)
        Schema::create('contratos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escritorio_id')->constrained('escritorios');
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('funcionario_id')->constrained('funcionarios');
            $table->foreignId('estabelecimento_id')->nullable()->constrained('estabelecimentos');
            $table->string('situacao', 30);

            // dados preenchidos pela empresa (RN-582)
            $table->date('data_admissao_prevista');
            $table->string('cargo')->nullable();
            $table->decimal('salario', 15, 2)->nullable();
            $table->string('unidade_salario', 20)->default('mes');
            $table->string('tipo_contrato', 30)->nullable();
            $table->unsignedSmallInteger('experiencia_dias_1')->nullable();
            $table->unsignedSmallInteger('experiencia_dias_2')->nullable();
            $table->date('data_termino')->nullable();
            $table->decimal('jornada_semanal', 5, 2)->nullable();
            $table->string('horario')->nullable();

            // dados técnicos preenchidos pelo escritório (RN-597)
            $table->string('matricula', 30)->nullable();
            $table->date('data_admissao')->nullable();
            $table->string('categoria', 3)->nullable();
            $table->string('cbo', 6)->nullable();
            $table->string('natureza_atividade', 10)->nullable();
            $table->string('regime_jornada', 30)->nullable();
            $table->string('sindicato_cnpj', 14)->nullable();
            $table->string('recibo_esocial')->nullable();

            // fluxo e responsáveis (RN-550, RN-592)
            $table->foreignId('responsavel_id')->nullable()->constrained('users');
            $table->foreignId('solicitado_por_id')->nullable()->constrained('users');
            $table->timestamp('solicitado_em')->nullable();
            $table->foreignId('atendimento_iniciado_por_id')->nullable()->constrained('users');
            $table->timestamp('atendimento_iniciado_em')->nullable();
            $table->foreignId('admitido_por_id')->nullable()->constrained('users');
            $table->timestamp('admitido_em')->nullable();
            $table->text('motivo_devolucao')->nullable();
            $table->text('motivo_cancelamento')->nullable();

            // desligamento (RN-540)
            $table->string('motivo_desligamento', 2)->nullable();
            $table->date('data_aviso')->nullable();
            $table->date('data_desligamento')->nullable();
            $table->string('tipo_aviso', 20)->nullable();
            $table->unsignedSmallInteger('dias_aviso')->nullable();
            $table->date('data_projetada_fim_aviso')->nullable();
            $table->string('pensao_alimenticia', 30)->nullable();
            $table->decimal('valor_liquido_rescisao', 15, 2)->nullable();
            $table->text('observacoes_desligamento')->nullable();
            $table->foreignId('desligado_por_id')->nullable()->constrained('users');
            $table->timestamp('desligado_em')->nullable();

            $table->timestamps();

            $table->index(['escritorio_id', 'situacao']);
            $table->index(['escritorio_id', 'admitido_por_id', 'admitido_em']);
            $table->index(['escritorio_id', 'atendimento_iniciado_por_id', 'atendimento_iniciado_em']);
        });

        // RN-575 linha do tempo
        Schema::create('contrato_historicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('contratos');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('acao', 40);
            $table->string('situacao_anterior', 30)->nullable();
            $table->string('situacao_nova', 30)->nullable();
            $table->text('descricao')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrato_historicos');
        Schema::dropIfExists('contratos');
        Schema::dropIfExists('dependentes');
        Schema::dropIfExists('funcionarios');
    }
};
