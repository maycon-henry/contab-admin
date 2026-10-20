<?php

namespace App\Actions\Despesas;

use App\Enums\Permissao;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Despesa;
use App\Models\User;

/** regras.md RN-1603 — exclusão lógica (o registro é preservado). */
class ExcluirDespesa
{
    public function executar(Despesa $despesa, User $user): void
    {
        RegraDeNegocioException::se(
            $despesa->escritorio_id !== $user->escritorio_id || ! $user->can(Permissao::DespesasGerenciar->value),
            'Você não tem permissão para excluir despesas desta empresa.',
        );

        $despesa->delete();
    }
}
