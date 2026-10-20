<?php

namespace App\Actions\Admissao;

use App\Domain\Admissao\VerificadorAdmissao;
use App\Enums\Permissao;
use App\Enums\SituacaoContrato;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Contrato;
use App\Models\User;
use App\Notifications\AdmissaoAtualizadaNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Notification;

/** regras.md RN-597, RN-598 e RN-550 (admitido por — nunca sobrescrito, RN-551). */
class ConcluirAdmissao
{
    public function __construct(private readonly VerificadorAdmissao $verificador) {}

    public function executar(Contrato $contrato, User $user, array $dadosTecnicos): void
    {
        RegraDeNegocioException::se(! $user->can(Permissao::AdmissoesAnalisar->value), 'Você não tem permissão.');
        RegraDeNegocioException::se($contrato->situacao !== SituacaoContrato::EmAnalise, 'Somente admissões em análise podem ser concluídas.');
        RegraDeNegocioException::se($contrato->responsavel_id !== $user->id && ! $user->ehAdministrador(),
            'Assuma a admissão antes de concluí-la (RN-592).');
        RegraDeNegocioException::se($contrato->admitido_por_id !== null, 'Esta admissão já foi concluída (RN-551).');

        $contrato->fill(Arr::only($dadosTecnicos, [
            'matricula', 'data_admissao', 'categoria', 'cbo', 'natureza_atividade', 'regime_jornada', 'sindicato_cnpj', 'recibo_esocial',
        ]));

        $pendencias = $this->verificador->pendenciasDoEscritorio($contrato->load('documentos'));
        if ($pendencias) {
            throw RegraDeNegocioException::comPendencias('Ainda há pendências para concluir a admissão (RN-598).', $pendencias);
        }

        $anterior = $contrato->situacao;
        $contrato->situacao = SituacaoContrato::Admitido;
        $contrato->admitido_por_id = $user->id;
        $contrato->admitido_em = now();
        $contrato->save();

        $contrato->registrarHistorico('concluida', "Funcionário admitido por {$user->name}.", $anterior, $user);

        Notification::send($contrato->empresa->usuarios()->where('ativo', true)->get(), new AdmissaoAtualizadaNotification($contrato, 'concluida'));
    }
}
