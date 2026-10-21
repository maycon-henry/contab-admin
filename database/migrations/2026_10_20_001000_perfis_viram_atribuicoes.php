<?php

use App\Actions\Usuarios\DefinirAtribuicoes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /** Atribuições equivalentes a cada perfil antigo — quem tinha o perfil mantém o mesmo acesso. */
    private const EQUIVALENCIA = [
        'Operacional' => ['empresas', 'usuarios_portal', 'admissoes', 'documentos', 'notas_fiscais'],
        'Financeiro' => ['cobranca', 'despesas'],
    ];

    /** regras.md RN-220 — os perfis fixos dão lugar às atribuições marcadas por colaborador. */
    public function up(): void
    {
        foreach (DB::table('escritorios')->pluck('id') as $escritorioId) {
            app(DefinirAtribuicoes::class)->garantirNoEscritorio($escritorioId);

            foreach (self::EQUIVALENCIA as $perfil => $atribuicoes) {
                $perfilId = DB::table('roles')->where('escritorio_id', $escritorioId)->where('name', $perfil)->value('id');
                if (! $perfilId) {
                    continue;
                }

                $novos = DB::table('roles')->where('escritorio_id', $escritorioId)->whereIn('name', $atribuicoes)->pluck('id');

                foreach (DB::table('model_has_roles')->where('role_id', $perfilId)->get() as $vinculo) {
                    foreach ($novos as $roleId) {
                        DB::table('model_has_roles')->insertOrIgnore([
                            'role_id' => $roleId,
                            'model_type' => $vinculo->model_type,
                            'model_id' => $vinculo->model_id,
                            'escritorio_id' => $escritorioId,
                        ]);
                    }
                }

                DB::table('model_has_roles')->where('role_id', $perfilId)->delete();
                DB::table('role_has_permissions')->where('role_id', $perfilId)->delete();
                DB::table('roles')->where('id', $perfilId)->delete();
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // sem volta automática: as atribuições podem ter sido alteradas depois da conversão
    }
};
