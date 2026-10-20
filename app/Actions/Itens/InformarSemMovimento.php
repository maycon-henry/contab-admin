<?php

namespace App\Actions\Itens;

use App\Enums\SituacaoItem;
use App\Enums\TipoItem;
use App\Exceptions\RegraDeNegocioException;
use App\Models\ItemCompetencia;
use App\Models\User;

/** regras.md RN-642 — a empresa informa "sem movimento"; o escritório confirma na avaliação. */
class InformarSemMovimento
{
    public function executar(ItemCompetencia $item, User $user, string $observacao): void
    {
        $item->loadMissing('competencia');

        RegraDeNegocioException::se($item->tipo !== TipoItem::Documento, 'Este item é uma tarefa do escritório.');
        RegraDeNegocioException::se($item->competencia->estaFechada(), 'A competência está fechada (RN-644).');
        RegraDeNegocioException::se(! in_array($item->situacao, [SituacaoItem::Pendente, SituacaoItem::Recusado], true),
            'Este item já foi enviado e está em avaliação ou aceito.');

        $item->update([
            'situacao' => SituacaoItem::EmAvaliacao,
            'sem_movimento' => true,
            'observacao_empresa' => $observacao,
            'enviado_por_id' => $user->id,
            'enviado_em' => now(),
        ]);
    }
}
