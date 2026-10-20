<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // regras.md seção 4 — escritórios (tenants)
        Schema::create('escritorios', function (Blueprint $table) {
            $table->id();
            $table->string('razao_social');
            $table->string('cnpj', 14)->unique();
            $table->string('crc')->nullable();
            $table->string('situacao', 20)->default('ativo');
            // regras.md seção 16.1 — cobrança diária
            $table->boolean('cobranca_automatica_ativa')->default(true);
            $table->unsignedTinyInteger('dia_corte_cobranca')->default(5);
            $table->timestamps();
        });

        // regras.md seção 5 — usuários
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escritorio_id')->constrained('escritorios');
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->string('tipo', 30);
            $table->boolean('titular')->default(false);
            $table->boolean('ativo')->default(true);
            $table->string('convite_token', 80)->nullable()->unique();
            $table->timestamp('convite_expira_em')->nullable();
            $table->timestamp('convite_aceito_em')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index(['escritorio_id', 'tipo']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // regras.md RN-330 — registro de acessos
        Schema::create('acessos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escritorio_id')->nullable()->constrained('escritorios');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('email')->nullable();
            $table->string('evento', 30);
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['escritorio_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acessos');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('escritorios');
    }
};
