<?php

namespace App\Filament\Portal\Resources\LotesImportacao;

use App\Filament\Compartilhado\ArquivosLoteRelationManager;
use App\Filament\Compartilhado\RelatorioLote;
use App\Filament\Portal\Resources\LotesImportacao\Pages\ListLotes;
use App\Filament\Portal\Resources\LotesImportacao\Pages\ViewLote;
use App\Models\LoteImportacaoXml;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

/** Portal — resultado de cada envio de XMLs (RN-703); fora do menu, acessado pela tela de Notas Fiscais */
class LoteImportacaoResource extends Resource
{
    protected static ?string $model = LoteImportacaoXml::class;

    protected static ?string $tenantRelationshipName = 'lotesImportacao';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'envio de XML';

    protected static ?string $pluralModelLabel = 'histórico de envios';

    protected static ?string $slug = 'envios-xml';

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
