<?php

namespace App\Domain\Rotina;

use App\Enums\AplicacaoTipoDocumento;
use App\Models\Empresa;
use App\Models\EmpresaRotinaAjuste;
use App\Models\TipoDocumento;
use Illuminate\Support\Collection;

/**
 * Rotina mensal vigente de uma empresa — regras.md seção 9.3, AGENTS.md seção 3.5.
 * Única fonte da regra: usada na geração mensal, na tela da rotina e na API.
 */
class CalculadoraRotinaMensal
{
    /**
     * @return Collection<int, ItemDaRotina>
     */
    public function calcular(Empresa $empresa, bool $incluirDispensados = false): Collection
    {
        $tipos = TipoDocumento::withoutGlobalScopes()
            ->where('escritorio_id', $empresa->escritorio_id)
            ->where('ativo', true)
            ->with('empresasSelecionadas:id')
            ->orderBy('tipo')->orderBy('nome')
            ->get();

        $ajustes = EmpresaRotinaAjuste::where('empresa_id', $empresa->id)->get()->keyBy('tipo_documento_id');

        return $tipos
            ->filter(fn (TipoDocumento $tipo) => $this->seAplica($tipo, $empresa))
            ->map(function (TipoDocumento $tipo) use ($ajustes) {
                /** @var EmpresaRotinaAjuste|null $ajuste */
                $ajuste = $ajustes->get($tipo->id);

                return new ItemDaRotina(
                    tipo: $tipo,
                    prazoDia: $ajuste?->prazo_dia ?? $tipo->prazo_dia,
                    obrigatorio: $ajuste?->obrigatorio ?? $tipo->obrigatorio,
                    dispensado: (bool) $ajuste?->dispensado,
                    justificativaDispensa: $ajuste?->justificativa,
                    ajustado: $ajuste !== null,
                );
            })
            ->filter(fn (ItemDaRotina $item) => $incluirDispensados || ! $item->dispensado)
            ->values();
    }

    public function seAplica(TipoDocumento $tipo, Empresa $empresa): bool
    {
        return match ($tipo->aplicacao) {
            AplicacaoTipoDocumento::Todas => true,
            AplicacaoTipoDocumento::PorRegime => in_array($empresa->regime_tributario->value, $tipo->regimes ?? [], true),
            AplicacaoTipoDocumento::Selecionadas => $tipo->empresasSelecionadas->contains('id', $empresa->id),
        };
    }
}
