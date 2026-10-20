<?php

namespace App\Models\Concerns;

use App\Enums\TipoUsuario;
use App\Models\Empresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Segundo nível de isolamento — AGENTS.md seção 3.4, regras.md RN-240.
 * Usuários da empresa só enxergam registros das empresas a que estão vinculados,
 * em qualquer painel ou na API.
 */
trait PertenceAEmpresa
{
    public static function bootPertenceAEmpresa(): void
    {
        static::addGlobalScope('empresa_do_usuario', function (Builder $query) {
            if (! Auth::hasUser()) {
                return;
            }

            $user = Auth::user();

            if ($user->tipo === TipoUsuario::UsuarioEmpresa) {
                $query->whereIn($query->qualifyColumn('empresa_id'), $user->empresasIds());
            }
        });
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
