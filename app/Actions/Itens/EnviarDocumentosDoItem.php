<?php

namespace App\Actions\Itens;

use App\Enums\Permissao;
use App\Enums\ResultadoCertidao;
use App\Enums\SituacaoItem;
use App\Enums\TipoItem;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Documento;
use App\Models\ItemCompetencia;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * regras.md RN-626, RN-641, RN-643, RN-644, RN-646.
 * Enviado pela empresa, o item vai para avaliação; enviado pelo escritório, já nasce aceito (RN-646).
 */
class EnviarDocumentosDoItem
{
    /**
     * @param  array<string, string>  $arquivos  caminho no disco => nome original
     */
    public function executar(ItemCompetencia $item, User $user, array $arquivos, ?string $validade = null, ?ResultadoCertidao $resultadoCertidao = null): void
    {
        $item->loadMissing('competencia');
        $peloEscritorio = $user->ehDaEquipe();

        RegraDeNegocioException::se($item->tipo !== TipoItem::Documento, 'Este item é uma tarefa do escritório.');
        RegraDeNegocioException::se($item->competencia->estaFechada(), 'A competência está fechada (RN-644).');
        RegraDeNegocioException::se(! in_array($item->situacao, [SituacaoItem::Pendente, SituacaoItem::Recusado], true),
            'Este documento já foi enviado e está em avaliação ou aceito (RN-643).');
        RegraDeNegocioException::se($peloEscritorio && ! $user->can(Permissao::DocumentosAvaliar->value),
            'Você não tem permissão para enviar documentos em nome da empresa (RN-646).');
        RegraDeNegocioException::se($arquivos === [], 'Anexe pelo menos um arquivo.');
        RegraDeNegocioException::se($item->exige_validade && blank($validade), 'Informe a data de validade do documento.');

        DB::transaction(function () use ($item, $user, $arquivos, $validade, $resultadoCertidao, $peloEscritorio) {
            foreach ($arquivos as $caminho => $nomeOriginal) {
                Documento::withoutGlobalScopes()->create([
                    'escritorio_id' => $item->escritorio_id,
                    'empresa_id' => $item->empresa_id,
                    'documentavel_type' => $item->getMorphClass(),
                    'documentavel_id' => $item->id,
                    'tipo' => 'item_competencia',
                    'arquivo' => $caminho,
                    'nome_original' => $nomeOriginal,
                    'origem' => $peloEscritorio ? 'escritorio' : 'empresa',
                    'visivel_empresa' => true,
                    'validade' => $validade,
                    'enviado_por_id' => $user->id,
                ]);
            }

            $item->update([
                'situacao' => SituacaoItem::EmAvaliacao,
                'sem_movimento' => false,
                'validade' => $validade,
                'enviado_por_id' => $user->id,
                'enviado_em' => now(),
                'motivo_recusa' => null,
                'complemento_recusa' => null,
            ]);

            // RN-646 — o próprio escritório conferiu o documento: aceite registrado no histórico de avaliações (RN-655)
            if ($peloEscritorio) {
                app(AceitarItem::class)->executar($item, $user, $validade, $resultadoCertidao);
            }
        });
    }
}
