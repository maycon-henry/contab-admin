<?php

namespace App\Actions\Cobranca;

use App\Domain\Cobranca\SelecionadorEmpresasParaCobranca;
use App\Models\CobrancaDocumentacao;
use App\Models\Empresa;
use App\Models\ItemCompetencia;
use App\Models\User;
use App\Notifications\CobrancaDocumentacaoNotification;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/** regras.md RN-1315 a RN-1321. */
class EnviarCobrancaDocumentacao
{
    public function __construct(private readonly SelecionadorEmpresasParaCobranca $selecionador) {}

    /** @return string resultado do envio */
    public function executar(Empresa $empresa, CarbonImmutable $data, string $tipo = 'automatica', ?User $enviadoPor = null): string
    {
        $itens = $this->selecionador->itensCobraveis($empresa, $data);

        if ($itens->isEmpty()) {
            return 'sem_pendencias'; // RN-1314 — nunca envia e-mail sem pendências
        }

        $destinatarios = $empresa->usuarios()->where('ativo', true)->whereNotNull('convite_aceito_em')->get();
        $referencia = $this->selecionador->competenciaReferencia($data);

        $resumoItens = $itens->map(fn (ItemCompetencia $item) => [
            'id' => $item->id,
            'nome' => $item->nome,
            'competencia' => $item->competencia->mes()->paraTela(),
            'prazo' => $item->prazo->format('d/m/Y'),
            'situacao' => $item->situacao->getLabel(),
            'dias_atraso' => $item->prazo->lt($data) ? (int) $item->prazo->diffInDays($data) : 0,
            'motivo_recusa' => $item->motivo_recusa?->getLabel(),
            'instrucoes' => $item->tipoDocumento?->instrucoes,
        ])->values()->all();

        $jaEnviada = $tipo === 'automatica' && CobrancaDocumentacao::withoutGlobalScopes()
            ->where('empresa_id', $empresa->id)->whereDate('data_referencia', $data)->where('tipo', 'automatica')->exists();
        if ($jaEnviada) {
            return 'ja_enviada';
        }

        try {
            // RN-1320 — o índice único garante uma cobrança automática por dia mesmo em execuções simultâneas
            // (a transação cria um savepoint: a violação não invalida uma transação externa)
            $cobranca = DB::transaction(fn () => CobrancaDocumentacao::withoutGlobalScopes()->create([
                'escritorio_id' => $empresa->escritorio_id,
                'empresa_id' => $empresa->id,
                'data_referencia' => $data->toDateString(),
                'tipo' => $tipo === 'manual' ? 'manual-'.now()->format('His') : $tipo,
                'competencia_referencia' => $referencia->paraBanco(),
                'destinatarios' => $destinatarios->pluck('email')->all(),
                'itens' => $resumoItens,
                'resultado' => $destinatarios->isEmpty() ? 'sem_destinatario' : 'enviada',
                'enviado_por_id' => $enviadoPor?->id,
            ]));
        } catch (UniqueConstraintViolationException) {
            return 'ja_enviada';
        }

        if ($destinatarios->isEmpty()) {
            return 'sem_destinatario'; // RN-1318 — alerta aparece para o escritório
        }

        Notification::send($destinatarios, new CobrancaDocumentacaoNotification($empresa, $referencia->paraTela(), $resumoItens));
        ItemCompetencia::withoutGlobalScopes()->whereIn('id', $itens->pluck('id'))->increment('vezes_cobrado');

        return $cobranca->resultado;
    }
}
