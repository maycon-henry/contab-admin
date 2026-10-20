<?php

namespace App\Actions\Desligamento;

use App\Enums\Permissao;
use App\Enums\SituacaoContrato;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Contrato;
use App\Models\User;

/** regras.md RN-548 — cancelamento enquanto não concluído, com justificativa. */
class CancelarDesligamento
{
    public function executar(Contrato $contrato, User $user, string $justificativa): void
    {
        RegraDeNegocioException::se(! $user->can(Permissao::DesligamentosGerenciar->value), 'Você não tem permissão para desligamentos.');
        RegraDeNegocioException::se($contrato->situacao !== SituacaoContrato::EmDesligamento, 'Não há desligamento em andamento.');
        RegraDeNegocioException::se(blank($justificativa), 'Informe a justificativa.');

        $anterior = $contrato->situacao;
        $contrato->update([
            'situacao' => SituacaoContrato::Admitido,
            'motivo_desligamento' => null, 'tipo_aviso' => null, 'data_aviso' => null, 'data_desligamento' => null,
            'dias_aviso' => null, 'data_projetada_fim_aviso' => null,
        ]);
        $contrato->registrarHistorico('desligamento_cancelado', $justificativa, $anterior, $user);
    }
}
