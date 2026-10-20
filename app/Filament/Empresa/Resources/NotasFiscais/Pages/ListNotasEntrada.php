<?php

namespace App\Filament\Empresa\Resources\NotasFiscais\Pages;

use App\Filament\Compartilhado\AcaoImportarXml;
use App\Filament\Empresa\Resources\LotesImportacao\LoteImportacaoResource;
use App\Filament\Empresa\Resources\NotasFiscais\NotaEntradaResource;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;

class ListNotasEntrada extends ListRecords
{
    protected static string $resource = NotaEntradaResource::class;

    public function getSubheading(): ?string
    {
        return Filament::getTenant()->razao_social.' — notas de compra e demais entradas, organizadas pela competência da emissão.';
    }

    protected function getHeaderActions(): array
    {
        return [
            AcaoImportarXml::make(fn () => Filament::getTenant(), fn ($lote) => LoteImportacaoResource::getUrl('view', ['record' => $lote])),
            Action::make('envios')->label('Envios de XML')->icon('heroicon-o-inbox-arrow-down')->color('gray')
                ->url(LoteImportacaoResource::getUrl('index')),
        ];
    }
}
