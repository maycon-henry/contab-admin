<?php

namespace App\Domain\Consultas;

use App\Enums\SituacaoCompetencia;
use App\Enums\SituacaoEmpresa;
use App\Enums\SituacaoItem;
use App\Enums\TipoItem;
use App\Models\Empresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Empresas devendo documentação — regras.md RN-1511 a RN-1515.
 * Agregação em SQL; a regra de "atraso" (RN-625) é a mesma do modelo ItemCompetencia.
 * Reutilizável pelo widget e pela API.
 */
class ConsultaEmpresasDevendoDocumentacao
{
    /** Subconsulta dos itens em atraso de competências abertas. */
    public function itensEmAtraso(?string $competencia = null, ?int $tipoDocumentoId = null, bool $somenteObrigatorios = false)
    {
        return DB::table('itens_competencia as i')
            ->join('competencias as c', 'c.id', '=', 'i.competencia_id')
            ->where('i.tipo', TipoItem::Documento->value)
            ->whereIn('i.situacao', array_map(fn ($s) => $s->value, SituacaoItem::cobraveis()))
            ->whereDate('i.prazo', '<', today())
            ->where('c.situacao', SituacaoCompetencia::Aberta->value)
            ->when($competencia, fn ($q) => $q->whereDate('c.competencia', $competencia))
            ->when($tipoDocumentoId, fn ($q) => $q->where('i.tipo_documento_id', $tipoDocumentoId))
            ->when($somenteObrigatorios, fn ($q) => $q->where('i.obrigatorio', true));
    }

    public function query(?string $competencia = null, ?int $tipoDocumentoId = null, bool $somenteObrigatorios = false): Builder
    {
        $agregado = $this->itensEmAtraso($competencia, $tipoDocumentoId, $somenteObrigatorios)
            ->groupBy('i.empresa_id')
            ->select([
                'i.empresa_id',
                DB::raw('count(*) as documentos_atrasados'),
                DB::raw('sum(case when i.obrigatorio then 1 else 0 end) as obrigatorios_atrasados'),
                DB::raw("sum(case when i.situacao = '".SituacaoItem::Recusado->value."' then 1 else 0 end) as recusados"),
                DB::raw('min(c.competencia) as competencia_mais_antiga'),
                DB::raw('min(i.prazo) as prazo_mais_antigo'),
            ]);

        return Empresa::query()
            ->where('empresas.situacao', SituacaoEmpresa::Ativa)
            ->joinSub($agregado, 'atrasos', 'atrasos.empresa_id', '=', 'empresas.id')
            ->select('empresas.*', 'atrasos.documentos_atrasados', 'atrasos.obrigatorios_atrasados', 'atrasos.recusados',
                'atrasos.competencia_mais_antiga', 'atrasos.prazo_mais_antigo');
    }

    public function totalEmpresas(): int
    {
        return $this->query()->count();
    }
}
