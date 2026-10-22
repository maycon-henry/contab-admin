<?php

namespace App\Actions\Admissao;

use App\Enums\DocumentoAdmissao;
use App\Enums\Permissao;
use App\Enums\SituacaoContrato;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Contrato;
use App\Models\Documento;
use App\Models\User;

/** regras.md RN-593 — cada documento é aceito ou recusado (com motivo), registrando quem avaliou e quando. */
class AvaliarDocumentoAdmissao
{
    public function executar(Documento $documento, User $user, bool $aceito, ?string $motivo = null): void
    {
        $contrato = $documento->documentavel;

        RegraDeNegocioException::se(! $contrato instanceof Contrato, 'Documento não pertence a uma admissão.');
        RegraDeNegocioException::se(! $user->can(Permissao::AdmissoesAnalisar->value), 'Você não tem permissão.');
        // RN-593 durante a admissão; RN-567 para documento trocado de funcionário já admitido
        $docDeAdmitidoEmAnalise = $contrato->situacao === SituacaoContrato::Admitido && $documento->avaliacao === null && ! $documento->substituido;
        RegraDeNegocioException::se($contrato->situacao !== SituacaoContrato::EmAnalise && ! $docDeAdmitidoEmAnalise,
            'Este documento não está aguardando avaliação.');
        RegraDeNegocioException::se(! $aceito && blank($motivo), 'Informe o motivo da recusa.');

        $documento->update([
            'avaliacao' => $aceito ? 'aceito' : 'recusado',
            'motivo_recusa' => $aceito ? null : $motivo,
            // quem do escritório aceitou ou recusou, e quando
            'avaliado_por_id' => $user->id,
            'avaliado_em' => now(),
        ]);

        if ($docDeAdmitidoEmAnalise) {
            $rotulo = DocumentoAdmissao::tryFrom((string) $documento->tipo)?->getLabel() ?? $documento->tipo;
            $contrato->registrarHistorico($aceito ? 'documento_aceito' : 'documento_recusado',
                $rotulo.($aceito ? ' aceito.' : ' recusado: '.$motivo), $contrato->situacao, $user);
        }
    }
}
