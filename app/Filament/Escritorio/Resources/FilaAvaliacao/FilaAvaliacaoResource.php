<?php

namespace App\Filament\Escritorio\Resources\FilaAvaliacao;

use App\Enums\Permissao;
use App\Enums\SituacaoItem;
use App\Filament\Escritorio\Acoes\AcoesItem;
use App\Filament\Escritorio\Resources\FilaAvaliacao\Pages\ListFilaAvaliacao;
use App\Models\ItemCompetencia;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

/** regras.md RN-657 — fila de avaliação de documentos de todas as empresas */
class FilaAvaliacaoResource extends Resource
{
    protected static ?string $model = ItemCompetencia::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Rotina mensal';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Fila de avaliação';

    protected static ?string $modelLabel = 'documento';

    protected static ?string $pluralModelLabel = 'Fila de avaliação';

    protected static ?string $slug = 'fila-de-avaliacao';

    public static function canViewAny(): bool
    {
        return auth()->user()->can(Permissao::DocumentosAvaliar->value);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $total = ItemCompetencia::where('situacao', SituacaoItem::EmAvaliacao)->count();

        return $total ? (string) $total : null;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->somenteDocumentos()->with(['empresa', 'competencia']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('enviado_em')->label('Enviado em')->dateTime('d/m/Y H:i')->sortable()->placeholder('—'),
                TextColumn::make('empresa.razao_social')->label('Empresa')->searchable(),
                TextColumn::make('competencia.competencia')->label('Competência')->date('m/Y'),
                // nome e observação cortados em 30 caracteres para a fila caber na tela; o texto inteiro fica no tooltip
                TextColumn::make('nome')->label('Documento')->searchable()->limit(30)
                    ->description(fn (ItemCompetencia $record) => $record->sem_movimento ? Str::limit('Sem movimento: '.$record->observacao_empresa, 30) : null)
                    ->tooltip(fn (ItemCompetencia $record) => $record->nome.($record->sem_movimento ? ' — sem movimento informado: '.$record->observacao_empresa : '')),
                TextColumn::make('prazo')->label('Prazo')->date('d/m/Y'),
                TextColumn::make('situacao')->label('Situação')->badge(),
            ])
            ->filters([
                SelectFilter::make('situacao')->label('Situação')->options(SituacaoItem::class)->default(SituacaoItem::EmAvaliacao->value),
                SelectFilter::make('empresa_id')->label('Empresa')->relationship('empresa', 'razao_social')->searchable()->preload(),
                SelectFilter::make('tipo_documento_id')->label('Tipo de documento')->relationship('tipoDocumento', 'nome'),
            ])
            ->recordActions([
                ...AcoesItem::todas(),
            ])
            ->defaultSort('enviado_em');
    }

    public static function getPages(): array
    {
        return ['index' => ListFilaAvaliacao::route('/')];
    }
}
