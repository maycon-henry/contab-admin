<?php

namespace App\Actions\Admissao;

use App\Domain\Admissao\VerificadorAdmissao;
use App\Enums\Permissao;
use App\Enums\SituacaoContrato;
use App\Enums\TipoUsuario;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Contrato;
use App\Models\User;
use App\Notifications\AdmissaoSolicitadaNotification;
use Illuminate\Support\Facades\Notification;

/** regras.md RN-586, RN-587 e RN-595 (nova solicitação após devolução). */
class SolicitarAdmissao
{
    public function __construct(private readonly VerificadorAdmissao $verificador) {}

    /** @return array<string> alertas não impeditivos (RN-524, RN-589) */
    public function executar(Contrato $contrato, User $user): array
    {
        if ($user->tipo === TipoUsuario::UsuarioEmpresa) {
            RegraDeNegocioException::se(! in_array($contrato->empresa_id, $user->empresasIds(), true), 'Empresa não vinculada ao usuário.');
        } else {
            RegraDeNegocioException::se(! $user->can(Permissao::AdmissoesAnalisar->value), 'Você não tem permissão.');
        }

        RegraDeNegocioException::se(! $contrato->situacao->editavelPelaEmpresa(), 'Esta admissão já foi solicitada.');

        $pendencias = $this->verificador->pendenciasDaEmpresa($contrato);
        if ($pendencias) {
            throw RegraDeNegocioException::comPendencias('Ainda há pendências para solicitar a admissão (RN-586).', $pendencias);
        }

        $erros = $this->verificador->errosLegais($contrato);
        if ($erros) {
            throw RegraDeNegocioException::comPendencias('A admissão não atende às regras legais.', $erros);
        }

        $anterior = $contrato->situacao;
        $contrato->update([
            'situacao' => SituacaoContrato::EmAnalise,
            'solicitado_por_id' => $contrato->solicitado_por_id ?? $user->id,
            'solicitado_em' => $contrato->solicitado_em ?? now(),
            'motivo_devolucao' => null,
        ]);

        $contrato->registrarHistorico(
            $anterior === SituacaoContrato::Devolvida ? 'solicitada_novamente' : 'solicitada',
            $anterior === SituacaoContrato::Devolvida ? 'Admissão corrigida e solicitada novamente.' : 'Admissão solicitada.',
            $anterior,
            $user,
        );

        // RN-576 — escritório é avisado (o responsável, se já houver; senão a equipe)
        $destinatarios = $contrato->responsavel
            ? collect([$contrato->responsavel])
            : User::where('escritorio_id', $contrato->escritorio_id)->daEquipe()->where('ativo', true)->get();
        Notification::send($destinatarios, new AdmissaoSolicitadaNotification($contrato));

        return $this->verificador->alertas($contrato);
    }
}
