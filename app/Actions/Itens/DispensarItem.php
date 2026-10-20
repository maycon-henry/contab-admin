<?php

namespace App\Actions\Itens;

use App\Enums\Permissao;
use App\Enums\SituacaoItem;
use App\Exceptions\RegraDeNegocioException;
use App\Models\ItemCompetencia;
use App\Models\User;

/** regras.md RN-622 / RN-624 — marcar como "não se aplica" com justificativa. */
class DispensarItem
{
    public function executar(ItemCompetencia $item, User $user, string $justificativa): void
    {
        $item->loadMissing('competencia');

        RegraDeNegocioException::se(! $user->can(Permissao::CompetenciasGerenciar->value), 'Você não tem permissão para dispensar itens.');
        RegraDeNegocioException::se($item->competencia->estaFechada(), 'A competência está fechada (RN-632).');
        RegraDeNegocioException::se(blank($justificativa), 'Informe a justificativa.');

        $item->avaliacoes()->create([
            'user_id' => $user->id,
            'resultado' => SituacaoItem::NaoSeAplica->value,
            'complemento' => $justificativa,
        ]);

        $item->update([
            'situacao' => SituacaoItem::NaoSeAplica,
            'complemento_recusa' => $justificativa,
            'avaliado_por_id' => $user->id,
            'avaliado_em' => now(),
        ]);
    }
}
