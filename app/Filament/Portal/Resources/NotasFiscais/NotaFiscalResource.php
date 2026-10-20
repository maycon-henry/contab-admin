<?php

namespace App\Filament\Portal\Resources\NotasFiscais;

use App\Filament\Compartilhado\TabelaNotas;
use App\Filament\Portal\Resources\NotasFiscais\Pages\ListNotasFiscais;
use App\Models\NotaFiscal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Portal — notas fiscais da empresa (regras.md RN-700 e RN-744) */
class NotaFiscalResource extends Resource
{
    protected static ?string $model = NotaFiscal::class;

    protected static ?string $tenantRelationshipName = 'notasFiscais';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'nota fiscal';

    protected static ?string $pluralModelLabel = 'notas fiscais';

    protected static ?string $slug = 'notas-fiscais';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        $tabela = TabelaNotas::configurar($table, mostrarCompetencia: true);

        return $tabela->filters([
            SelectFilter::make('competencia')->label('Competência')
                ->options(fn () => NotaFiscal::query()->select('competencia')->distinct()->orderByDesc('competencia')->pluck('competencia')
                    ->mapWithKeys(fn ($c) => [$c->toDateString() => $c->format('m/Y')])),
            ...$tabela->getFilters(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListNotasFiscais::route('/')];
    }
}
