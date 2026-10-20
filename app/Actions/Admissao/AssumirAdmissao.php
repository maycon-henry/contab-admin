<?php

namespace App\Actions\Admissao;

use App\Enums\Permissao;
use App\Enums\SituacaoContrato;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Contrato;
use App\Models\User;

/** regras.md RN-592 e RN-550 (atendimento iniciado por — gravado só na primeira vez). */
class AssumirAdmissao
{
    public function executar(Contrato $contrato, User $user): void
    {
        RegraDeNegocioException::se(! $user->can(Permissao::AdmissoesAnalisar->value), 'Você não tem permissão para analisar admissões.');
        RegraDeNegocioException::se($contrato->situacao !== SituacaoContrato::EmAnalise, 'Somente admissões em análise podem ser assumidas.');
        RegraDeNegocioException::se($contrato->responsavel_id !== null, 'Esta admissão já tem responsável. Peça uma transferência (RN-592).');

        $contrato->update([
            'responsavel_id' => $user->id,
            'atendimento_iniciado_por_id' => $contrato->atendimento_iniciado_por_id ?? $user->id,
            'atendimento_iniciado_em' => $contrato->atendimento_iniciado_em ?? now(),
        ]);

        $contrato->registrarHistorico('assumida', "Atendimento assumido por {$user->name}.", $contrato->situacao, $user);
    }
}
