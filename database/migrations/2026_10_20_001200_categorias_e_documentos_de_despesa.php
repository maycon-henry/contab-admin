<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * regras.md RN-1606, RN-1609 e RN-1610 — categoria da despesa, vários documentos por despesa
     * (boleto, recibo, nota fiscal, cheque) e a configuração de formatos/tamanho de cada tipo.
     * O boleto que já estava anexado vira um documento do tipo "boleto".
     */
    public function up(): void
    {
        Schema::create('categorias_despesa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escritorio_id')->constrained('escritorios');
            $table->string('nome', 80);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['escritorio_id', 'nome']);
        });

        Schema::table('despesas', function (Blueprint $table) {
            $table->foreignId('categoria_despesa_id')->nullable()->after('empresa_id')->constrained('categorias_despesa');
        });

        Schema::create('config_documentos_despesa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escritorio_id')->constrained('escritorios');
            $table->string('tipo', 30);
            $table->json('formatos');
            $table->unsignedSmallInteger('tamanho_maximo_mb');
            $table->timestamps();

            $table->unique(['escritorio_id', 'tipo']);
        });

        foreach (DB::table('despesas')->whereNotNull('boleto_caminho')->get() as $despesa) {
            DB::table('documentos')->insert([
                'escritorio_id' => $despesa->escritorio_id,
                'empresa_id' => $despesa->empresa_id,
                'documentavel_type' => 'App\Models\Despesa',
                'documentavel_id' => $despesa->id,
                'tipo' => 'boleto',
                'arquivo' => $despesa->boleto_caminho,
                'nome_original' => $despesa->boleto_nome ?: basename($despesa->boleto_caminho),
                'origem' => 'escritorio',
                'visivel_empresa' => true,
                'enviado_por_id' => $despesa->cadastrada_por_id,
                'created_at' => $despesa->created_at,
                'updated_at' => $despesa->updated_at,
            ]);
        }

        Schema::table('despesas', function (Blueprint $table) {
            $table->dropColumn(['boleto_caminho', 'boleto_nome']);
        });
    }

    public function down(): void
    {
        Schema::table('despesas', function (Blueprint $table) {
            $table->string('boleto_caminho')->nullable();
            $table->string('boleto_nome')->nullable();
            $table->dropConstrainedForeignId('categoria_despesa_id');
        });

        Schema::dropIfExists('config_documentos_despesa');
        Schema::dropIfExists('categorias_despesa');
    }
};
