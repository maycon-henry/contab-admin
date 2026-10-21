<?php

namespace App\Filament\Portal\Resources\Despesas;

use App\Enums\TipoUsuario;
use App\Filament\Empresa\Resources\Despesas\DespesaResource as DespesaDaEmpresaResource;
use App\Filament\Portal\Resources\Despesas\Pages\ManageDespesas;

/**
 * Portal — a empresa cadastra e paga as próprias despesas (regras.md RN-1607).
 * Mesmo formulário, tabela e Actions do painel do escritório.
 */
class DespesaResource extends DespesaDaEmpresaResource
{
    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return auth()->user()->tipo === TipoUsuario::UsuarioEmpresa;
    }

    public static function getPages(): array
    {
        return ['index' => ManageDespesas::route('/')];
    }
}
