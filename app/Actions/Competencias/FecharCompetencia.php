<?php

namespace App\Actions\Competencias;

use App\Enums\Permissao;
use App\Enums\SituacaoCompetencia;
use App\Enums\SituacaoItem;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Competencia;
use App\Models\User;

/** regras.md RN-631 e RN-632. */
class FecharCompetencia
{
    public function executar(Competencia $competencia, User $user): void
    {
        RegraDeNegocioException::se(! $user->can(Permissao::CompetenciasGerenciar->value), 'Você não tem permissão para fechar competências.');
        RegraDeNegocioException::se($competencia->estaFechada(), 'A competência já está fechada.');

        $pendentes = $competencia->itens()
            ->where('obrigatorio', true)
            ->whereNotIn('situacao', SituacaoItem::resolvidas())
            ->pluck('nome')
            ->all();

        if ($pendentes) {
            throw RegraDeNegocioException::comPendencias('Existem itens obrigatórios não resolvidos (RN-631).', $pendentes);
        }

        $competencia->update([
            'situacao' => SituacaoCompetencia::Fechada,
            'fechada_por_id' => $user->id,
            'fechada_em' => now(),
        ]);
    }
}
