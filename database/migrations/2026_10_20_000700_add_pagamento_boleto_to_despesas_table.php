<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** regras.md RN-1605 (pagamento) e RN-1606 (boleto). */
    public function up(): void
    {
        Schema::table('despesas', function (Blueprint $table) {
            $table->string('boleto_caminho')->nullable()->after('data_vencimento');
            $table->string('boleto_nome')->nullable()->after('boleto_caminho');
            $table->decimal('valor_pago', 15, 2)->nullable()->after('boleto_nome');
            $table->date('data_pagamento')->nullable()->after('valor_pago');
            $table->foreignId('pagamento_registrado_por_id')->nullable()->after('data_pagamento')->constrained('users');
            $table->timestamp('pagamento_registrado_em')->nullable()->after('pagamento_registrado_por_id');
        });
    }

    public function down(): void
    {
        Schema::table('despesas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pagamento_registrado_por_id');
            $table->dropColumn(['boleto_caminho', 'boleto_nome', 'valor_pago', 'data_pagamento', 'pagamento_registrado_em']);
        });
    }
};
