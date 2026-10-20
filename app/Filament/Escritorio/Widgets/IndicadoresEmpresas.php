<?php

namespace App\Filament\Escritorio\Widgets;

use App\Domain\Consultas\ConsultaEmpresasDevendoDocumentacao;
use App\Enums\Permissao;
use App\Enums\SituacaoEmpresa;
use App\Enums\SituacaoItem;
use App\Filament\Escritorio\Resources\Empresas\EmpresaResource;
use App\Filament\Escritorio\Resources\FilaAvaliacao\FilaAvaliacaoResource;
use App\Models\Empresa;
use App\Models\ItemCompetencia;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** regras.md RN-1510 e RN-1511 */
class IndicadoresEmpresas extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected static bool $isDiscovered = false;

    public static function canView(): bool
    {
        return auth()->user()->can(Permissao::DashboardAdministrador->value);
    }

    protected function getStats(): array
    {
        $porSituacao = Empresa::query()->selectRaw('situacao, count(*) as total')->groupBy('situacao')->pluck('total', 'situacao');
        $total = $porSituacao->sum();
        $devendo = app(ConsultaEmpresasDevendoDocumentacao::class)->totalEmpresas();
        $emAvaliacao = ItemCompetencia::where('situacao', SituacaoItem::EmAvaliacao)->count();

        return [
            Stat::make('Empresas', $total)
                ->description(sprintf('%d ativas · %d suspensas · %d encerradas',
                    $porSituacao[SituacaoEmpresa::Ativa->value] ?? 0, $porSituacao[SituacaoEmpresa::Suspensa->value] ?? 0, $porSituacao[SituacaoEmpresa::Encerrada->value] ?? 0))
                ->icon('heroicon-o-building-office-2')
                ->url(EmpresaResource::getUrl('index')),
            Stat::make('Empresas devendo documentação', $devendo)
                ->description($total ? round($devendo / max(1, $porSituacao[SituacaoEmpresa::Ativa->value] ?? 1) * 100).'% das empresas ativas' : '—')
                ->color($devendo ? 'danger' : 'success')
                ->icon('heroicon-o-exclamation-triangle'),
            Stat::make('Documentos aguardando avaliação', $emAvaliacao)
                ->description('Pendência do escritório, não da empresa (RN-1515)')
                ->color($emAvaliacao ? 'info' : 'gray')
                ->icon('heroicon-o-clipboard-document-check')
                ->url(FilaAvaliacaoResource::getUrl('index')),
        ];
    }
}
