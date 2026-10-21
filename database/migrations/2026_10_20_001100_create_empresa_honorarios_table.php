<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** regras.md RN-1200, RN-1201 e RN-1203 — honorário mensal vigente e histórico de reajustes. */
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->decimal('honorario_valor', 15, 2)->nullable();
            $table->unsignedTinyInteger('honorario_dia_vencimento')->nullable();
        });

        Schema::create('empresa_honorarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->decimal('valor', 15, 2);
            $table->unsignedTinyInteger('dia_vencimento');
            $table->date('vigencia_inicio');
            $table->date('vigencia_fim')->nullable();
            $table->foreignId('registrado_por_id')->nullable()->constrained('users'); // sem cascade (RN-233)
            $table->timestamps();

            $table->index(['empresa_id', 'vigencia_inicio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_honorarios');

        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['honorario_valor', 'honorario_dia_vencimento']);
        });
    }
};
