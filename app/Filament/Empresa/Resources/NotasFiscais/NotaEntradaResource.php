<?php

namespace App\Filament\Empresa\Resources\NotasFiscais;

use App\Enums\ClassificacaoNota;
use App\Filament\Empresa\Resources\NotasFiscais\Pages\ListNotasEntrada;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

/** Notas fiscais de entrada da empresa (terceiros e emissão própria — RN-716). */
class NotaEntradaResource extends NotasFiscaisResource
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Notas Fiscais de Entrada';

    protected static ?string $pluralModelLabel = 'notas fiscais de entrada';

    protected static ?string $slug = 'notas-fiscais-entrada';

    public static function classificacoes(): array
    {
        return [ClassificacaoNota::EntradaTerceiros, ClassificacaoNota::EntradaPropria];
    }

    public static function getPages(): array
    {
        return ['index' => ListNotasEntrada::route('/')];
    }
}
