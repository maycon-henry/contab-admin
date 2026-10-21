<?php

namespace App\Actions\Admissao;

use App\Enums\DocumentoAdmissao;
use App\Enums\Permissao;
use App\Enums\SituacaoContrato;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Contrato;
use App\Models\Documento;
use App\Models\User;
use App\Notifications\AdmissaoAtualizadaNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * regras.md RN-593 e RN-595 — devolve à empresa para correção.
 * Os documentos marcados na devolução são recusados (com o motivo de cada um) antes de devolver,
 * e a lista dos recusados entra no motivo que a empresa vê.
 */
class DevolverAdmissao
{
    public function __construct(private readonly AvaliarDocumentoAdmissao $avaliarDocumento) {}

    /**
     * @param  array<int, string>  $documentosRecusados  id do documento => motivo da recusa
     */
    public function executar(Contrato $contrato, User $user, ?string $motivo, array $documentosRecusados = []): void
    {
        RegraDeNegocioException::se(! $user->can(Permissao::AdmissoesAnalisar->value), 'Você não tem permissão.');
        RegraDeNegocioException::se($contrato->situacao !== SituacaoContrato::EmAnalise, 'Somente admissões em análise podem ser devolvidas.');
        RegraDeNegocioException::se(blank($motivo) && $documentosRecusados === [], 'Marque o documento com problema ou informe o motivo da devolução.');

        DB::transaction(function () use ($contrato, $user, $motivo, $documentosRecusados) {
            foreach ($documentosRecusados as $documentoId => $motivoDocumento) {
                $documento = $contrato->documentos()->where('substituido', false)->find($documentoId);
                RegraDeNegocioException::se($documento === null, 'Documento não pertence a esta admissão.');
                RegraDeNegocioException::se(blank($motivoDocumento), 'Informe o motivo de cada documento recusado.');

                $this->avaliarDocumento->executar($documento, $user, false, $motivoDocumento);
            }

            $recusados = $contrato->documentos()->where('substituido', false)->where('avaliacao', 'recusado')->get()
                ->map(fn (Documento $d) => '• '.(DocumentoAdmissao::tryFrom((string) $d->tipo)?->getLabel() ?? $d->tipo).': '.$d->motivo_recusa);
            $texto = trim(collect([trim((string) $motivo), $recusados->isEmpty() ? null : "Documentos recusados:\n".$recusados->implode("\n")])->filter()->implode("\n\n"));

            $anterior = $contrato->situacao;
            $contrato->update(['situacao' => SituacaoContrato::Devolvida, 'motivo_devolucao' => $texto]);
            $contrato->registrarHistorico('devolvida', $texto, $anterior, $user);
        });

        Notification::send($contrato->empresa->usuarios()->where('ativo', true)->get(), new AdmissaoAtualizadaNotification($contrato, 'devolvida'));
    }
}
