<?php

namespace App\Filament\Escritorio\Resources\CategoriasDespesa;

use App\Actions\Despesas\SalvarCategoriaDespesa;
use App\Enums\Permissao;
use App\Filament\Escritorio\Resources\CategoriasDespesa\Pages\ManageCategoriasDespesa;
use App\Filament\Support\Acao;
use App\Models\CategoriaDespesa;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/** regras.md RN-1609 — categorias de despesa do escritório (somente o administrador). */
class CategoriaDespesaResource extends Resource
{
    protected static ?string $model = CategoriaDespesa::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Configurações';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Categorias de despesa';

    protected static ?string $modelLabel = 'categoria de despesa';

    protected static ?string $pluralModelLabel = 'categorias de despesa';

    protected static ?string $slug = 'categorias-despesa';

    public static function canViewAny(): bool
    {
        return auth()->user()->can(Permissao::EscritorioConfigurar->value);
    }

    public static function canDelete($record): bool
    {
        return false; // inativar, nunca excluir: despesas antigas continuam com a categoria
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nome')->label('Nome')->required()->maxLength(80)->placeholder('Ex.: Aluguel, Energia, Fornecedores'),
            Toggle::make('ativo')->label('Ativa')->default(true)->hiddenOn('create')
                ->helperText('Categoria inativa não aparece em novas despesas; as antigas continuam com ela.'),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')->label('Nome')->searchable()->sortable(),
                TextColumn::make('despesas_count')->label('Despesas')->counts('despesas')->sortable(),
                IconColumn::make('ativo')->label('Ativa')->boolean(),
            ])
            ->filters([TernaryFilter::make('ativo')->label('Ativa')])
            ->recordActions([
                EditAction::make()->using(function (CategoriaDespesa $record, array $data, EditAction $action) {
                    Acao::rodar(fn () => app(SalvarCategoriaDespesa::class)->atualizar($record, auth()->user(), $data['nome'], (bool) ($data['ativo'] ?? true)), action: $action);

                    return $record;
                }),
            ])
            ->emptyStateHeading('Nenhuma categoria cadastrada')
            ->emptyStateDescription('Toda despesa das empresas precisa de uma categoria.')
            ->defaultSort('nome');
    }

    public static function getPages(): array
    {
        return ['index' => ManageCategoriasDespesa::route('/')];
    }
}
