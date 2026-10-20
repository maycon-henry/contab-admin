<?php

namespace App\Models\Concerns;

use App\Models\Escritorio;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Isolamento por escritório (tenant) — AGENTS.md seção 3.4, regras.md RN-100.
 * Com usuário autenticado, toda consulta é filtrada pelo escritório dele e todo registro novo
 * recebe o escritório dele. Sem usuário (jobs, comandos, seeders) não há filtro: o código
 * chamador trabalha com IDs explícitos.
 */
trait PertenceAoEscritorio
{
    public static function bootPertenceAoEscritorio(): void
    {
        static::addGlobalScope('escritorio', function (Builder $query) {
            if ($escritorioId = static::escritorioDoUsuario()) {
                $query->where($query->qualifyColumn('escritorio_id'), $escritorioId);
            }
        });

        static::creating(function ($model) {
            if (empty($model->escritorio_id) && ($escritorioId = static::escritorioDoUsuario())) {
                $model->escritorio_id = $escritorioId;
            }
        });
    }

    protected static function escritorioDoUsuario(): ?int
    {
        return Auth::hasUser() ? Auth::user()->escritorio_id : null;
    }

    public function escritorio(): BelongsTo
    {
        return $this->belongsTo(Escritorio::class);
    }
}
