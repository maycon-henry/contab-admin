<?php

namespace App\Domain\Consultas;

use App\Enums\SituacaoCompetencia;
use App\Enums\SituacaoContrato;
use App\Enums\SituacaoItem;
use App\Enums\TipoItem;
use App\Models\Contrato;
use App\Models\Despesa;
use App\Models\Documento;
use App\Models\Empresa;
use App\Models\ItemCompetencia;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ações pendentes de uma empresa — regras.md RN-1520 a RN-1524.
 * Usada pelo painel da empresa e, futuramente, pela API. Cada grupo separa
 * o que depende da empresa do que depende do escritório.
 */
class PendenciasDaEmpresa
{
    /** Dias à frente considerados "a vencer" para despesas (RN-1523). */
    public const DIAS_A_VENCER = 7;

    public function __construct(private readonly Empresa $empresa) {}

    public static function para(Empresa $empresa): self
    {
        return new self($empresa);
    }

    /** Itens de competências abertas. */
    private function itens(): Builder
    {
        return ItemCompetencia::query()
            ->where('empresa_id', $this->empresa->id)
            ->whereHas('competencia', fn (Builder $query) => $query->where('situacao', SituacaoCompetencia::Aberta));
    }

    /** RN-1521 — documentos que a empresa ainda deve (pendentes ou recusados). */
    public function documentosDaEmpresa(): Builder
    {
        return $this->itens()->where('tipo', TipoItem::Documento)->whereIn('situacao', SituacaoItem::cobraveis());
    }

    /** RN-1521 — documentos enviados aguardando avaliação do escritório. */
    public function documentosParaAvaliar(): Builder
    {
        return $this->itens()->where('situacao', SituacaoItem::EmAvaliacao);
    }

    /** RN-1521 — tarefas mensais do escritório ainda não concluídas. */
    public function tarefasDoEscritorio(): Builder
    {
        return $this->itens()->where('tipo', TipoItem::Tarefa)->where('situacao', SituacaoItem::Pendente);
    }

    /** RN-1522 — admissões e desligamentos em andamento. */
    public function movimentosDeFuncionarios(): Builder
    {
        return Contrato::query()->where('empresa_id', $this->empresa->id)
            ->whereIn('situacao', [SituacaoContrato::Rascunho, SituacaoContrato::Devolvida, SituacaoContrato::EmAnalise, SituacaoContrato::EmDesligamento]);
    }

    /** RN-567 — documentos trocados pela empresa em funcionário já admitido, aguardando o Operacional. */
    public function documentosDeFuncionariosParaAvaliar(): Builder
    {
        return Documento::query()->where('empresa_id', $this->empresa->id)
            ->where('substituido', false)->whereNull('avaliacao')
            ->whereHasMorph('documentavel', [Contrato::class], fn (Builder $query) => $query->where('situacao', SituacaoContrato::Admitido));
    }

    /** RN-1523 — despesas vencidas e não pagas. */
    public function despesasVencidas(): Builder
    {
        return Despesa::query()->where('empresa_id', $this->empresa->id)
            ->whereNull('data_pagamento')->whereDate('data_vencimento', '<', today());
    }

    /** RN-1523 — despesas não pagas que vencem nos próximos dias. */
    public function despesasAVencer(): Builder
    {
        return Despesa::query()->where('empresa_id', $this->empresa->id)
            ->whereNull('data_pagamento')
            ->whereDate('data_vencimento', '>=', today())
            ->whereDate('data_vencimento', '<=', today()->addDays(self::DIAS_A_VENCER));
    }

    /** Quem precisa agir em um contrato em andamento. */
    public static function responsavelPeloContrato(Contrato $contrato): string
    {
        return in_array($contrato->situacao, [SituacaoContrato::Rascunho, SituacaoContrato::Devolvida], true) ? 'Empresa' : 'Escritório';
    }

    public function total(): int
    {
        return $this->documentosDaEmpresa()->count() + $this->documentosParaAvaliar()->count() + $this->tarefasDoEscritorio()->count()
            + $this->movimentosDeFuncionarios()->count() + $this->documentosDeFuncionariosParaAvaliar()->count() + $this->despesasVencidas()->count() + $this->despesasAVencer()->count();
    }
}
