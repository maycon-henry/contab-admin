<?php

namespace App\Filament\Empresa\Resources\Documentos;

use App\Domain\Competencia as Mes;
use App\Enums\Permissao;
use App\Enums\SituacaoItem;
use App\Filament\Compartilhado\AcaoEnviarDocumentoDoItem;
use App\Filament\Empresa\Resources\Documentos\Pages\ListDocumentos;
use App\Filament\Escritorio\Acoes\AcoesItem;
use App\Filament\Portal\Resources\DocumentosDoMes\DocumentoDoMesResource;
use App\Models\ItemCompetencia;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Documentos mensais da empresa selecionada, do lado do escritório — regras.md seções 9.4 a 9.7.
 * O escritório envia o documento em nome da empresa e ele já fica aceito (RN-646);
 * o que a empresa enviou é avaliado aqui mesmo.
 */
class DocumentoResource extends Resource
{
    protected static ?string $model = ItemCompetencia::class;

    protected static ?string $tenantRelationshipName = 'itensCompetencia';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentArrowUp;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Documentos';

    protected static ?string $modelLabel = 'documento';

    protected static ?string $pluralModelLabel = 'documentos';

    protected static ?string $slug = 'documentos';

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
        $total = ItemCompetencia::somenteDocumentos()->whereIn('situacao', SituacaoItem::cobraveis())
            ->where('empresa_id', filament()->getTenant()?->getKey())
            ->whereHas('competencia', fn (Builder $query) => $query->where('situacao', 'aberta'))->count();

        return $total ? (string) $total : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->somenteDocumentos()->with('competencia', 'tipoDocumento', 'enviadoPor');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')->label('Documento')->weight('medium')->searchable()->limit(40)
                    ->tooltip(fn (ItemCompetencia $r) => $r->nome),
                TextColumn::make('competencia.competencia')->label('Competência')->date('m/Y'),
                TextColumn::make('prazo')->label('Prazo')->date('d/m/Y')
                    ->color(fn (ItemCompetencia $r) => $r->estaAtrasado() ? 'danger' : null)
                    ->description(fn (ItemCompetencia $r) => $r->estaAtrasado() ? "Atrasado há {$r->diasDeAtraso()} dia(s)" : null),
                TextColumn::make('situacao')->label('Situação')->badge()
                    ->description(fn (ItemCompetencia $r) => match (true) {
                        $r->situacao === SituacaoItem::Recusado => 'Motivo: '.$r->motivo_recusa?->getLabel(),
                        $r->aceito_por_validade => 'Documento anterior ainda válido',
                        default => null,
                    }),
                TextColumn::make('enviado_em')->label('Enviado')->date('d/m/Y')->placeholder('—')
                    ->description(fn (ItemCompetencia $r) => $r->enviadoPor ? ($r->enviadoPor->ehDaEquipe() ? 'pelo escritório' : 'pela empresa') : null),
            ])
            ->filters([
                // mesma lógica de competência do portal: padrão é o mês anterior
                SelectFilter::make('competencia')->label('Competência')
                    ->options(fn () => DocumentoDoMesResource::competenciasDisponiveis())
                    ->default(Mes::padrao()->paraBanco())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn ($q, $v) => $q->whereHas('competencia', fn ($q) => $q->whereDate('competencia', $v)))),
                SelectFilter::make('situacao')->label('Situação')->options(SituacaoItem::class),
            ])
            ->recordActions([
                // RN-646 — enviado pelo escritório, já fica aceito
                AcaoEnviarDocumentoDoItem::make(fn (ItemCompetencia $record) => $record),
                AcoesItem::aceitar(),
                AcoesItem::recusar(),
                AcoesItem::dispensar(),
                AcoesItem::verArquivos(),
            ])
            ->defaultSort('prazo');
    }

    public static function getPages(): array
    {
        return ['index' => ListDocumentos::route('/')];
    }
}
