<?php

namespace App\Actions\Competencias;

use App\Domain\Competencia as Mes;
use App\Domain\Rotina\CalculadoraRotinaMensal;
use App\Enums\SituacaoCompetencia;
use App\Enums\SituacaoItem;
use App\Enums\TipoItem;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Competencia;
use App\Models\Empresa;
use App\Models\ItemCompetencia;
use Illuminate\Support\Facades\DB;

/**
 * Busca a competência de uma empresa; se não existir, cria com os itens da rotina mensal.
 * Idempotente — regras.md RN-620, RN-621, RN-656; AGENTS.md seção 3.5.
 */
class ObterCompetencia
{
    public function __construct(private readonly CalculadoraRotinaMensal $rotina) {}

    public function executar(Empresa $empresa, Mes $mes): Competencia
    {
        RegraDeNegocioException::se($mes->ehFutura(), 'Competências futuras não podem ser abertas (RN-603).');

        $existente = Competencia::withoutGlobalScopes()
            ->where('empresa_id', $empresa->id)
            ->whereDate('competencia', $mes->paraBanco())
            ->first();

        if ($existente) {
            return $existente;
        }

        return DB::transaction(function () use ($empresa, $mes) {
            $competencia = Competencia::withoutGlobalScopes()->firstOrCreate(
                ['empresa_id' => $empresa->id, 'competencia' => $mes->paraBanco()],
                ['escritorio_id' => $empresa->escritorio_id, 'situacao' => SituacaoCompetencia::Aberta],
            );

            if ($competencia->wasRecentlyCreated) {
                $this->gerarItens($competencia, $empresa, $mes);
            }

            return $competencia;
        });
    }

    private function gerarItens(Competencia $competencia, Empresa $empresa, Mes $mes): void
    {
        foreach ($this->rotina->calcular($empresa) as $item) {
            $tipo = $item->tipo;

            $dados = [
                'escritorio_id' => $empresa->escritorio_id,
                'empresa_id' => $empresa->id,
                'competencia_id' => $competencia->id,
                'tipo_documento_id' => $tipo->id,
                'nome' => $tipo->nome,
                'tipo' => $tipo->tipo,
                // cópia das configurações no momento da criação (RN-612)
                'prazo' => $mes->diaDoMesSeguinte($item->prazoDia),
                'obrigatorio' => $item->obrigatorio,
                'exige_validade' => $tipo->exige_validade,
                'eh_certidao' => $tipo->eh_certidao,
                'situacao' => SituacaoItem::Pendente,
            ];

            // RN-656 — aproveitamento de documento ainda válido
            if ($tipo->tipo === TipoItem::Documento && $tipo->aproveitar_valido && $tipo->exige_validade) {
                $anterior = ItemCompetencia::withoutGlobalScopes()
                    ->where('empresa_id', $empresa->id)
                    ->where('tipo_documento_id', $tipo->id)
                    ->where('situacao', SituacaoItem::Aceito)
                    ->whereDate('validade', '>=', $mes->ultimoDia()->toDateString())
                    ->latest('avaliado_em')
                    ->first();

                if ($anterior) {
                    $dados = array_merge($dados, [
                        'situacao' => SituacaoItem::Aceito,
                        'aceito_por_validade' => true,
                        'validade' => $anterior->validade,
                        'resultado_certidao' => $anterior->resultado_certidao,
                        'documento_aproveitado_id' => $anterior->documentos()->latest('id')->value('id'),
                        'avaliado_em' => now(),
                    ]);
                }
            }

            ItemCompetencia::withoutGlobalScopes()->create($dados);
        }
    }
}
