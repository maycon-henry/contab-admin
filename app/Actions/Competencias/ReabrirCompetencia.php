<?php

namespace App\Actions\Competencias;

use App\Enums\Permissao;
use App\Enums\SituacaoCompetencia;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Competencia;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/** regras.md RN-633 — exige permissão e justificativa. */
class ReabrirCompetencia
{
    public function executar(Competencia $competencia, User $user, string $justificativa): void
    {
        RegraDeNegocioException::se(! $user->can(Permissao::CompetenciasReabrir->value), 'Você não tem permissão para reabrir competências.');
        RegraDeNegocioException::se(! $competencia->estaFechada(), 'A competência já está aberta.');
        RegraDeNegocioException::se(blank($justificativa), 'Informe a justificativa.');

        $competencia->update(['situacao' => SituacaoCompetencia::Aberta, 'fechada_por_id' => null, 'fechada_em' => null]);

        Log::channel('stack')->info('Competência reaberta', [
            'competencia_id' => $competencia->id,
            'user_id' => $user->id,
            'justificativa' => $justificativa,
        ]);
    }
}
