<?php

use App\Actions\Usuarios\DefinirPerfis;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * regras.md RN-221 — o perfil "Colaborador" passa a se chamar "Operacional" e todo
     * escritório ganha o perfil "Financeiro". Quem já tinha o perfil continua com ele.
     */
    public function up(): void
    {
        DB::table('roles')->where('name', 'Colaborador')->update(['name' => 'Operacional']);

        foreach (DB::table('escritorios')->pluck('id') as $escritorioId) {
            app(DefinirPerfis::class)->garantirNoEscritorio($escritorioId);
        }
    }

    public function down(): void
    {
        DB::table('roles')->where('name', 'Operacional')->update(['name' => 'Colaborador']);
    }
};
