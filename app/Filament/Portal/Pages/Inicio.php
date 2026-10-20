<?php

namespace App\Filament\Portal\Pages;

use App\Filament\Portal\Widgets\ResumoDoMes;
use Filament\Pages\Dashboard;
use Filament\Widgets\AccountWidget;

/** Tela inicial do portal — sugestão da decisão D3 (RN-1500): resumo do mês e admissões em andamento. */
class Inicio extends Dashboard
{
    protected static ?string $title = 'Início';

    protected static ?string $navigationLabel = 'Início';

    public function getWidgets(): array
    {
        return [AccountWidget::class, ResumoDoMes::class];
    }

    public function getColumns(): int|array
    {
        return 1;
    }
}
