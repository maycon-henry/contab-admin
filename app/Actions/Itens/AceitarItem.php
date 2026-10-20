<?php

namespace App\Actions\Itens;

use App\Enums\Permissao;
use App\Enums\ResultadoCertidao;
use App\Enums\SituacaoItem;
use App\Exceptions\RegraDeNegocioException;
use App\Models\ItemCompetencia;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** regras.md RN-650 a RN-655 — aceitar documento (ou confirmar "sem movimento"). */
class AceitarItem
{
    public function executar(ItemCompetencia $item, User $user, ?string $validade = null, ?ResultadoCertidao $resultadoCertidao = null): void
    {
        $item->loadMissing('competencia');

        RegraDeNegocioException::se(! $user->can(Permissao::DocumentosAvaliar->value), 'Você não tem permissão para avaliar documentos (RN-650).');
        RegraDeNegocioException::se($item->competencia->estaFechada(), 'A competência está fechada (RN-632).');
        RegraDeNegocioException::se($item->situacao !== SituacaoItem::EmAvaliacao, 'Somente itens em avaliação podem ser avaliados.');

        $novaSituacao = $item->sem_movimento ? SituacaoItem::NaoSeAplica : SituacaoItem::Aceito;

        if ($novaSituacao === SituacaoItem::Aceito) {
            if ($item->exige_validade) {
                RegraDeNegocioException::se(blank($validade), 'Confirme a data de validade (RN-652).');
                RegraDeNegocioException::se(Carbon::parse($validade)->lt(today()), 'Documento vencido não pode ser aceito (RN-652).');
            }
            if ($item->eh_certidao) {
                RegraDeNegocioException::se($resultadoCertidao === null, 'Informe o resultado da certidão (RN-653).');
            }
        }

        DB::transaction(function () use ($item, $user, $validade, $resultadoCertidao, $novaSituacao) {
            $item->avaliacoes()->create([
                'user_id' => $user->id,
                'resultado' => $novaSituacao->value,
                'validade' => $validade,
                'resultado_certidao' => $resultadoCertidao?->value,
            ]);

            $item->update([
                'situacao' => $novaSituacao,
                'validade' => $item->exige_validade ? $validade : $item->validade,
                'resultado_certidao' => $resultadoCertidao,
                'avaliado_por_id' => $user->id,
                'avaliado_em' => now(),
            ]);
        });
    }
}
