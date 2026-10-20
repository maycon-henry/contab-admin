<?php

namespace App\Actions\Itens;

use App\Enums\Permissao;
use App\Enums\SituacaoItem;
use App\Enums\TipoItem;
use App\Exceptions\RegraDeNegocioException;
use App\Models\ItemCompetencia;
use App\Models\User;

/** regras.md RN-624 — tarefas internas do escritório. */
class ConcluirTarefa
{
    public function executar(ItemCompetencia $item, User $user): void
    {
        $item->loadMissing('competencia');

        RegraDeNegocioException::se(! $user->can(Permissao::CompetenciasGerenciar->value), 'Você não tem permissão para concluir tarefas.');
        RegraDeNegocioException::se($item->tipo !== TipoItem::Tarefa, 'Somente tarefas podem ser concluídas.');
        RegraDeNegocioException::se($item->competencia->estaFechada(), 'A competência está fechada (RN-632).');

        $item->update(['situacao' => SituacaoItem::Concluido, 'avaliado_por_id' => $user->id, 'avaliado_em' => now()]);
    }
}
