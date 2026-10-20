<?php

namespace App\Filament\Escritorio\Pages;

use App\Filament\Escritorio\Widgets\EmpresasDevendoDocumentacao;
use App\Filament\Escritorio\Widgets\IndicadoresEmpresas;
use Filament\Pages\Dashboard;
use Filament\Widgets\AccountWidget;

/**
 * Tela inicial — regras.md RN-1500.
 * Administrador: indicadores (seção 18.2). Colaborador: apenas boas-vindas, por enquanto.
 * A visibilidade de cada widget é por permissão (AGENTS.md, Dashboards).
 */
class Painel extends Dashboard
{
    protected static ?string $title = 'Início';

    protected static ?string $navigationLabel = 'Início';

    public function getWidgets(): array
    {
        return [
            AccountWidget::class,
            IndicadoresEmpresas::class,
            EmpresasDevendoDocumentacao::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 1;
    }
}
