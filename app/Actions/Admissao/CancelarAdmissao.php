<?php

namespace App\Actions\Admissao;

use App\Enums\Permissao;
use App\Enums\SituacaoContrato;
use App\Enums\TipoUsuario;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Contrato;
use App\Models\User;
use App\Notifications\AdmissaoAtualizadaNotification;
use Illuminate\Support\Facades\Notification;

/** regras.md RN-584 (empresa, só rascunho) e RN-596 (escritório, com justificativa). */
class CancelarAdmissao
{
    public function executar(Contrato $contrato, User $user, ?string $justificativa = null): void
    {
        if ($user->tipo === TipoUsuario::UsuarioEmpresa) {
            RegraDeNegocioException::se($contrato->situacao !== SituacaoContrato::Rascunho,
                'Depois de solicitada, apenas o escritório pode cancelar a admissão (RN-596).');
        } else {
            RegraDeNegocioException::se(! $user->can(Permissao::AdmissoesAnalisar->value), 'Você não tem permissão.');
            RegraDeNegocioException::se(! $contrato->situacao->emAdmissao(), 'Esta admissão não pode mais ser cancelada.');
            RegraDeNegocioException::se($contrato->situacao !== SituacaoContrato::Rascunho && blank($justificativa), 'Informe a justificativa (RN-596).');
        }

        $anterior = $contrato->situacao;
        $contrato->update(['situacao' => SituacaoContrato::AdmissaoCancelada, 'motivo_cancelamento' => $justificativa]);
        $contrato->registrarHistorico('cancelada', $justificativa ?: 'Admissão cancelada.', $anterior, $user);

        if ($user->ehDaEquipe() && $anterior !== SituacaoContrato::Rascunho) {
            Notification::send($contrato->empresa->usuarios()->where('ativo', true)->get(), new AdmissaoAtualizadaNotification($contrato, 'cancelada'));
        }
    }
}
