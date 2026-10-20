<?php

namespace App\Filament\Empresa\Widgets;

use App\Domain\Consultas\PendenciasDaEmpresa;
use App\Filament\Empresa\Resources\Despesas\DespesaResource;
use App\Filament\Empresa\Resources\Funcionarios\FuncionarioResource;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Indicadores do painel da empresa — regras.md RN-1520. */
class ResumoPendencias extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $pendencias = PendenciasDaEmpresa::para(Filament::getTenant());

        $documentos = $pendencias->documentosDaEmpresa()->count();
        $atrasados = $pendencias->documentosDaEmpresa()->whereDate('prazo', '<', today())->count();
        $avaliar = $pendencias->documentosParaAvaliar()->count();
        $tarefas = $pendencias->tarefasDoEscritorio()->count();
        $funcionarios = $pendencias->movimentosDeFuncionarios()->count();
        $docsFuncionarios = $pendencias->documentosDeFuncionariosParaAvaliar()->count();
        $avaliar += $docsFuncionarios;
        $vencidas = $pendencias->despesasVencidas();
        $qtdVencidas = (clone $vencidas)->count();
        $valorVencidas = (float) (clone $vencidas)->sum('valor');
        $aVencer = $pendencias->despesasAVencer()->count();

        return [
            Stat::make('Documentos devidos pela empresa', $documentos)
                ->description($atrasados ? "{$atrasados} em atraso" : 'Nenhum em atraso')
                ->color($atrasados ? 'danger' : ($documentos ? 'warning' : 'success'))
                ->icon('heroicon-o-document-text'),
            Stat::make('Para o escritório fazer', $avaliar + $tarefas)
                ->description("{$avaliar} documento(s) para avaliar · {$tarefas} tarefa(s)")
                ->color($avaliar + $tarefas ? 'info' : 'success')
                ->icon('heroicon-o-clipboard-document-check'),
            Stat::make('Funcionários com pendência', $funcionarios)
                ->description('Admissões e desligamentos em andamento')
                ->color($funcionarios ? 'warning' : 'success')
                ->icon('heroicon-o-users')
                ->url(FuncionarioResource::getUrl('index')),
            Stat::make('Contas vencidas e não pagas', $qtdVencidas)
                ->description(($qtdVencidas ? 'R$ '.number_format($valorVencidas, 2, ',', '.').' · ' : '')."{$aVencer} a vencer em ".PendenciasDaEmpresa::DIAS_A_VENCER.' dias')
                ->color($qtdVencidas ? 'danger' : ($aVencer ? 'warning' : 'success'))
                ->icon('heroicon-o-banknotes')
                ->url(DespesaResource::getUrl('index')),
        ];
    }
}
