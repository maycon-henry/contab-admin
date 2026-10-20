<?php

namespace App\Actions\Admissao;

use App\Enums\Permissao;
use App\Enums\SituacaoContrato;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Contrato;
use App\Models\User;
use App\Notifications\AdmissaoAtualizadaNotification;
use Illuminate\Support\Facades\Notification;

/** regras.md RN-595 — devolve à empresa para correção. */
class DevolverAdmissao
{
    public function executar(Contrato $contrato, User $user, string $motivo): void
    {
        RegraDeNegocioException::se(! $user->can(Permissao::AdmissoesAnalisar->value), 'Você não tem permissão.');
        RegraDeNegocioException::se($contrato->situacao !== SituacaoContrato::EmAnalise, 'Somente admissões em análise podem ser devolvidas.');
        RegraDeNegocioException::se(blank($motivo), 'Informe o motivo da devolução.');

        $anterior = $contrato->situacao;
        $contrato->update(['situacao' => SituacaoContrato::Devolvida, 'motivo_devolucao' => $motivo]);
        $contrato->registrarHistorico('devolvida', $motivo, $anterior, $user);

        Notification::send($contrato->empresa->usuarios()->where('ativo', true)->get(), new AdmissaoAtualizadaNotification($contrato, 'devolvida'));
    }
}
