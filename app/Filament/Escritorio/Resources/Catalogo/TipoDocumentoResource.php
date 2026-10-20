<?php

namespace App\Filament\Escritorio\Resources\Catalogo;

use App\Domain\Opcoes;
use App\Enums\AplicacaoTipoDocumento;
use App\Enums\Permissao;
use App\Enums\RegimeTributario;
use App\Enums\TipoItem;
use App\Filament\Escritorio\Resources\Catalogo\Pages\CreateTipoDocumento;
use App\Filament\Escritorio\Resources\Catalogo\Pages\EditTipoDocumento;
use App\Filament\Escritorio\Resources\Catalogo\Pages\ListTiposDocumento;
use App\Models\TipoDocumento;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/** regras.md seção 9.2 — catálogo de documentos do escritório */
class TipoDocumentoResource extends Resource
{
    protected static ?string $model = TipoDocumento::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Configurações';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Catálogo de documentos';

    protected static ?string $modelLabel = 'item do catálogo';

    protected static ?string $pluralModelLabel = 'catálogo de documentos';

    protected static ?string $slug = 'catalogo-documentos';

    public static function canViewAny(): bool
    {
        return auth()->user()->can(Permissao::CatalogoGerenciar->value);
    }

    public static function canDelete($record): bool
    {
        return false; // RN-612 — inativar, nunca excluir
    }

    public static function form(Schema $schema): Schema
    {
        $ehDocumento = fn (Get $get) => in_array($get('tipo'), [TipoItem::Documento, TipoItem::Documento->value], true);

        return $schema->columns(3)->components([
            Section::make('Item')->columnSpan(2)->columns(2)->schema([
                TextInput::make('nome')->label('Nome')->required()->columnSpanFull()->placeholder('CND Federal — Receita/PGFN'),
                Select::make('tipo')->label('Tipo')->options(TipoItem::class)->required()->default(TipoItem::Documento->value)->live(),
                TextInput::make('prazo_dia')->label('Prazo (dia do mês seguinte)')->numeric()->minValue(1)->maxValue(31)->required()->default(10),
                Textarea::make('instrucoes')->label('Instruções para a empresa')->rows(3)->columnSpanFull()
                    ->helperText('O que é o documento e onde obtê-lo. Aparece no portal junto ao item.')->visible($ehDocumento),
                CheckboxList::make('formatos')->label('Formatos aceitos')->options(Opcoes::FORMATOS)->columns(4)->columnSpanFull()->visible($ehDocumento),
            ]),
            Section::make('Regras')->columnSpan(1)->schema([
                Toggle::make('obrigatorio')->label('Obrigatório para fechar o mês')->default(true),
                Toggle::make('multiplos_arquivos')->label('Aceita mais de um arquivo')->visible($ehDocumento),
                Toggle::make('exige_validade')->label('Exige data de validade')->live()->visible($ehDocumento),
                Toggle::make('eh_certidao')->label('É certidão (registra negativa/positiva)')->visible($ehDocumento),
                Toggle::make('aproveitar_valido')->label('Aproveitar documento ainda válido (RN-656)')
                    ->visible(fn (Get $get) => $ehDocumento($get) && $get('exige_validade')),
                Toggle::make('ativo')->label('Ativo')->default(true),
            ]),
            Section::make('A quem se aplica')->columnSpanFull()->columns(2)->schema([
                Select::make('aplicacao')->label('Aplicação')->options(AplicacaoTipoDocumento::class)->required()->default(AplicacaoTipoDocumento::Todas->value)->live(),
                CheckboxList::make('regimes')->label('Regimes')->options(RegimeTributario::class)->columns(2)
                    ->visible(fn (Get $get) => in_array($get('aplicacao'), [AplicacaoTipoDocumento::PorRegime, AplicacaoTipoDocumento::PorRegime->value], true)),
                Select::make('empresasSelecionadas')->label('Empresas')->multiple()->relationship('empresasSelecionadas', 'razao_social')->preload()
                    ->visible(fn (Get $get) => in_array($get('aplicacao'), [AplicacaoTipoDocumento::Selecionadas, AplicacaoTipoDocumento::Selecionadas->value], true)),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')->label('Nome')->searchable()->description(fn (TipoDocumento $r) => $r->tipo === TipoItem::Tarefa ? 'Tarefa do escritório' : null),
                TextColumn::make('prazo_dia')->label('Prazo')->formatStateUsing(fn ($state) => "Dia {$state}"),
                TextColumn::make('aplicacao')->label('Aplica-se a')->badge(),
                IconColumn::make('obrigatorio')->label('Obrig.')->boolean(),
                IconColumn::make('exige_validade')->label('Validade')->boolean(),
                IconColumn::make('eh_certidao')->label('Certidão')->boolean(),
                IconColumn::make('aproveitar_valido')->label('Aproveita válido')->boolean(),
                IconColumn::make('ativo')->label('Ativo')->boolean(),
            ])
            ->filters([TernaryFilter::make('ativo')->label('Ativo')->default(true)])
            ->recordActions([EditAction::make()])
            ->defaultSort('nome');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTiposDocumento::route('/'),
            'create' => CreateTipoDocumento::route('/create'),
            'edit' => EditTipoDocumento::route('/{record}/edit'),
        ];
    }
}
