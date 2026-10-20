<?php

namespace App\Domain\Consultas;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** regras.md RN-553 a RN-555 — agregação em SQL a partir das colunas de responsáveis (AGENTS.md 5.2.1). */
class RelatorioAdmissoesPorColaborador
{
    /** @return Collection<int, object> */
    public function gerar(int $escritorioId, CarbonInterface $de, CarbonInterface $ate, ?int $empresaId = null, ?int $colaboradorId = null): Collection
    {
        $periodo = [$de->copy()->startOfDay(), $ate->copy()->endOfDay()];

        $concluidas = DB::table('contratos')->where('escritorio_id', $escritorioId)->whereBetween('admitido_em', $periodo)
            ->when($empresaId, fn ($q) => $q->where('empresa_id', $empresaId))
            ->groupBy('admitido_por_id')
            ->select('admitido_por_id as user_id', DB::raw('count(*) as concluidas'),
                DB::raw('avg(extract(epoch from (admitido_em - solicitado_em)) / 86400) as dias_solicitacao'),
                DB::raw('avg(extract(epoch from (admitido_em - atendimento_iniciado_em)) / 86400) as dias_atendimento'))
            ->get()->keyBy('user_id');

        $iniciadas = DB::table('contratos')->where('escritorio_id', $escritorioId)->whereBetween('atendimento_iniciado_em', $periodo)
            ->when($empresaId, fn ($q) => $q->where('empresa_id', $empresaId))
            ->groupBy('atendimento_iniciado_por_id')->select('atendimento_iniciado_por_id as user_id', DB::raw('count(*) as total'))
            ->pluck('total', 'user_id');

        $devolucoes = DB::table('contrato_historicos as h')->join('contratos as c', 'c.id', '=', 'h.contrato_id')
            ->where('c.escritorio_id', $escritorioId)->where('h.acao', 'devolvida')->whereBetween('h.created_at', $periodo)
            ->when($empresaId, fn ($q) => $q->where('c.empresa_id', $empresaId))
            ->groupBy('h.user_id')->select('h.user_id', DB::raw('count(*) as total'))
            ->pluck('total', 'user_id');

        // RN-552 — colaboradores desativados continuam no relatório
        return User::where('escritorio_id', $escritorioId)->daEquipe()
            ->when($colaboradorId, fn ($q) => $q->whereKey($colaboradorId))
            ->orderBy('name')->get()
            ->map(fn (User $u) => (object) [
                'id' => $u->id,
                'nome' => $u->name.($u->ativo ? '' : ' (desativado)'),
                'concluidas' => (int) ($concluidas[$u->id]->concluidas ?? 0),
                'iniciadas' => (int) ($iniciadas[$u->id] ?? 0),
                'devolucoes' => (int) ($devolucoes[$u->id] ?? 0),
                'dias_solicitacao' => isset($concluidas[$u->id]) ? round((float) $concluidas[$u->id]->dias_solicitacao, 1) : null,
                'dias_atendimento' => isset($concluidas[$u->id]) ? round((float) $concluidas[$u->id]->dias_atendimento, 1) : null,
            ])
            ->filter(fn ($l) => $l->concluidas + $l->iniciadas + $l->devolucoes > 0 || $colaboradorId)
            ->sortByDesc('concluidas')
            ->values();
    }
}
