<?php

namespace App\Filament\Empresa\Resources\NotasFiscais;

use App\Enums\ClassificacaoNota;
use App\Filament\Empresa\Resources\NotasFiscais\Pages\ListNotasSaida;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

/** Notas fiscais de saída da empresa (RN-716). */
class NotaSaidaResource extends NotasFiscaisResource
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Notas Fiscais de Saída';

    protected static ?string $pluralModelLabel = 'notas fiscais de saída';

    protected static ?string $slug = 'notas-fiscais-saida';

    public static function classificacoes(): array
    {
        return [ClassificacaoNota::Saida];
    }

    public static function getPages(): array
    {
        return ['index' => ListNotasSaida::route('/')];
    }
}
