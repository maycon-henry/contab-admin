<?php

namespace App\Filament\Portal\Resources\LotesImportacao\Pages;

use App\Filament\Portal\Resources\LotesImportacao\LoteImportacaoResource;
use App\Filament\Portal\Resources\NotasFiscais\NotaFiscalResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

/** Histórico de envios de XML, aberto pelo botão da tela de Notas Fiscais (RN-703) */
class ListLotes extends ListRecords
{
    protected static string $resource = LoteImportacaoResource::class;

    protected static ?string $title = 'Histórico de envios';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('notas')->label('Voltar para Notas Fiscais')->icon('heroicon-o-arrow-left')->color('gray')
                ->url(NotaFiscalResource::getUrl('index')),
        ];
    }
}
