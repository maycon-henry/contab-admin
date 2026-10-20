<?php

namespace App\Filament\Portal\Widgets;

use App\Domain\Competencia as Mes;
use App\Enums\SituacaoContrato;
use App\Enums\SituacaoItem;
use App\Filament\Portal\Resources\DocumentosDoMes\DocumentoDoMesResource;
use App\Filament\Portal\Resources\Funcionarios\FuncionarioResource;
use App\Models\Contrato;
use App\Models\ItemCompetencia;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ResumoDoMes extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected function getStats(): array
    {
        $empresaId = filament()->getTenant()->getKey();
        $base = fn () => ItemCompetencia::somenteDocumentos()->where('empresa_id', $empresaId);

        $pendentes = $base()->where('situacao', SituacaoItem::Pendente)->count();
        $atrasados = $base()->atrasados()->count();
        $recusados = $base()->where('situacao', SituacaoItem::Recusado)->count();
        $emAvaliacao = $base()->where('situacao', SituacaoItem::EmAvaliacao)->count();
        $admissoes = Contrato::where('empresa_id', $empresaId)->whereIn('situacao', [SituacaoContrato::Rascunho, SituacaoContrato::EmAnalise, SituacaoContrato::Devolvida])->count();

        return [
            Stat::make('Documentos pendentes', $pendentes)->description("{$atrasados} atrasado(s)")->color($atrasados ? 'danger' : 'warning')
                ->url(DocumentoDoMesResource::getUrl('index')),
            Stat::make('Recusados pelo escritório', $recusados)->description('Envie novamente')->color($recusados ? 'danger' : 'gray')
                ->url(DocumentoDoMesResource::getUrl('index')),
            Stat::make('Aguardando o escritório', $emAvaliacao)->description('Documentos enviados em conferência')->color('info'),
            Stat::make('Admissões em andamento', $admissoes)->description('Competência de referência: '.Mes::padrao()->paraTela())
                ->url(FuncionarioResource::getUrl('index')),
        ];
    }
}
