<?php

namespace App\Filament\Empresa\Pages;

use App\Filament\Empresa\Widgets\AcoesPendentes;
use App\Filament\Empresa\Widgets\ResumoPendencias;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;

/** Página inicial da empresa selecionada: ações pendentes — regras.md RN-1520. */
class PaginaDaEmpresa extends Dashboard
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Página da Empresa';

    protected static ?string $title = 'Página da Empresa';

    public function getSubheading(): ?string
    {
        return Filament::getTenant()?->razao_social;
    }

    public function getWidgets(): array
    {
        return [ResumoPendencias::class, AcoesPendentes::class];
    }

    public function getColumns(): int|array
    {
        return 1;
    }
}
