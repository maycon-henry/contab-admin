<?php

namespace App\Filament\Empresa\Resources\LotesImportacao;

use App\Enums\Permissao;
use App\Filament\Compartilhado\ArquivosLoteRelationManager;
use App\Filament\Compartilhado\RelatorioLote;
use App\Filament\Empresa\Resources\LotesImportacao\Pages\ListLotes;
use App\Filament\Empresa\Resources\LotesImportacao\Pages\ViewLote;
use App\Models\LoteImportacaoXml;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

/** Envios de XML da empresa (RN-703) — acessado pelas telas de notas, fora do menu. */
class LoteImportacaoResource extends Resource
{
    protected static ?string $model = LoteImportacaoXml::class;

    protected static ?string $tenantRelationshipName = 'lotesImportacao';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'envio de XML';

    protected static ?string $pluralModelLabel = 'envios de XML';

    protected static ?string $slug = 'envios-xml';

    public static function canViewAny(): bool
    {
        return auth()->user()->can(Permissao::NotasVisualizar->value);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table->columns(RelatorioLote::colunas())->recordActions([ViewAction::make()->label('Resultado')])
            ->defaultSort('created_at', 'desc')->poll('5s');
    }

    public static function infolist(Schema $schema): Schema
    {
        return RelatorioLote::infolist($schema);
    }

    public static function getRelations(): array
    {
        return [ArquivosLoteRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => ListLotes::route('/'), 'view' => ViewLote::route('/{record}')];
    }
}
