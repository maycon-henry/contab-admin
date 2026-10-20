<?php

namespace App\Domain\Cobranca;

use App\Domain\Competencia;
use App\Enums\SituacaoCompetencia;
use App\Enums\SituacaoEmpresa;
use App\Enums\SituacaoEscritorio;
use App\Enums\SituacaoItem;
use App\Enums\TipoItem;
use App\Models\Empresa;
use App\Models\ItemCompetencia;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Regras de elegibilidade da cobrança diária — regras.md seção 16.1, AGENTS.md seção 3.6.
 */
class SelecionadorEmpresasParaCobranca
{
    /** RN-1310 — competência de referência é o mês anterior à data de envio. */
    public function competenciaReferencia(CarbonImmutable $data): Competencia
    {
        return Competencia::daData($data)->anterior();
    }

    /** RN-1312 — sugestão da decisão D18: apenas dias úteis. */
    public function ehDiaDeEnvio(CarbonImmutable $data): bool
    {
        return $data->isWeekday();
    }

    /**
     * Empresas que devem ser cobradas no dia (sem considerar destinatários — RN-1318 é tratado no envio).
     *
     * @return Collection<int, Empresa>
     */
    public function selecionar(CarbonImmutable $data, ?int $empresaId = null): Collection
    {
        return Empresa::withoutGlobalScopes()
            ->with('escritorio')
            ->where('situacao', SituacaoEmpresa::Ativa)
            ->whereHas('escritorio', fn ($q) => $q->where('situacao', SituacaoEscritorio::Ativo)->where('cobranca_automatica_ativa', true))
            ->when($empresaId, fn ($q) => $q->whereKey($empresaId))
            ->get()
            ->filter(fn (Empresa $empresa) => $this->deveCobrar($empresa, $data))
            ->values();
    }

    public function deveCobrar(Empresa $empresa, CarbonImmutable $data): bool
    {
        // RN-1317 — suspensão por período
        if ($empresa->cobranca_suspensa_ate && $data->lte($empresa->cobranca_suspensa_ate)) {
            return false;
        }

        // RN-1311 — data de corte (própria da empresa ou padrão do escritório)
        if ($data->day < $empresa->diaCorteCobranca()) {
            return false;
        }

        return $this->itensCobraveis($empresa, $data)->isNotEmpty();
    }

    /**
     * RN-1313 — documentos pendentes ou recusados da competência de referência e atrasados de competências anteriores abertas.
     *
     * @return Collection<int, ItemCompetencia>
     */
    public function itensCobraveis(Empresa $empresa, CarbonImmutable $data): Collection
    {
        $referencia = $this->competenciaReferencia($data);

        return ItemCompetencia::withoutGlobalScopes()
            ->with('competencia', 'tipoDocumento')
            ->where('empresa_id', $empresa->id)
            ->where('tipo', TipoItem::Documento)
            ->whereIn('situacao', SituacaoItem::cobraveis())
            ->whereHas('competencia', fn ($q) => $q->withoutGlobalScopes()
                ->where('situacao', SituacaoCompetencia::Aberta)
                ->whereDate('competencia', '<=', $referencia->paraBanco()))
            ->get()
            ->filter(fn (ItemCompetencia $item) => $item->competencia->mes()->igual($referencia) || $item->prazo->lt($data))
            ->sortBy('prazo')
            ->values();
    }
}
