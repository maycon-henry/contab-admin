<?php

namespace App\Actions\Itens;

use App\Enums\SituacaoItem;
use App\Enums\TipoItem;
use App\Enums\TipoUsuario;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Documento;
use App\Models\ItemCompetencia;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** regras.md RN-626, RN-641, RN-643, RN-644. */
class EnviarDocumentosDoItem
{
    /**
     * @param  array<string, string>  $arquivos  caminho no disco => nome original
     */
    public function executar(ItemCompetencia $item, User $user, array $arquivos, ?string $validade = null): void
    {
        $item->loadMissing('competencia');

        RegraDeNegocioException::se($item->tipo !== TipoItem::Documento, 'Este item é uma tarefa do escritório.');
        RegraDeNegocioException::se($item->competencia->estaFechada(), 'A competência está fechada (RN-644).');
        RegraDeNegocioException::se(! in_array($item->situacao, [SituacaoItem::Pendente, SituacaoItem::Recusado], true),
            'Este documento já foi enviado e está em avaliação ou aceito (RN-643).');
        RegraDeNegocioException::se($arquivos === [], 'Anexe pelo menos um arquivo.');
        RegraDeNegocioException::se($item->exige_validade && blank($validade), 'Informe a data de validade do documento.');

        DB::transaction(function () use ($item, $user, $arquivos, $validade) {
            foreach ($arquivos as $caminho => $nomeOriginal) {
                Documento::withoutGlobalScopes()->create([
                    'escritorio_id' => $item->escritorio_id,
                    'empresa_id' => $item->empresa_id,
                    'documentavel_type' => $item->getMorphClass(),
                    'documentavel_id' => $item->id,
                    'tipo' => 'item_competencia',
                    'arquivo' => $caminho,
                    'nome_original' => $nomeOriginal,
                    'origem' => $user->tipo === TipoUsuario::UsuarioEmpresa ? 'empresa' : 'escritorio',
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
        });
    }
}
