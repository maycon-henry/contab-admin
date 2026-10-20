<?php

namespace App\Actions\Itens;

use App\Enums\MotivoRecusa;
use App\Enums\Permissao;
use App\Enums\SituacaoItem;
use App\Exceptions\RegraDeNegocioException;
use App\Models\ItemCompetencia;
use App\Models\User;
use App\Notifications\ItemRecusadoNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/** regras.md RN-651, RN-654, RN-655 — recusar documento enviado. */
class RecusarItem
{
    public function executar(ItemCompetencia $item, User $user, MotivoRecusa $motivo, ?string $complemento = null): void
    {
        $item->loadMissing(['competencia', 'empresa.usuarios']);

        RegraDeNegocioException::se(! $user->can(Permissao::DocumentosAvaliar->value), 'Você não tem permissão para avaliar documentos (RN-650).');
        RegraDeNegocioException::se($item->competencia->estaFechada(), 'A competência está fechada (RN-632).');
        RegraDeNegocioException::se($item->situacao !== SituacaoItem::EmAvaliacao, 'Somente itens em avaliação podem ser avaliados.');

        DB::transaction(function () use ($item, $user, $motivo, $complemento) {
            $item->avaliacoes()->create([
                'user_id' => $user->id,
                'resultado' => SituacaoItem::Recusado->value,
                'motivo_recusa' => $motivo->value,
                'complemento' => $complemento,
            ]);

            $item->update([
                'situacao' => SituacaoItem::Recusado,
                'motivo_recusa' => $motivo,
                'complemento_recusa' => $complemento,
                'sem_movimento' => false,
                'avaliado_por_id' => $user->id,
                'avaliado_em' => now(),
            ]);
        });

        Notification::send($item->empresa->usuarios->where('ativo', true), new ItemRecusadoNotification($item));
    }
}
