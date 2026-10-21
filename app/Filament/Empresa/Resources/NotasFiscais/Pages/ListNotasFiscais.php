<?php

namespace App\Filament\Empresa\Resources\NotasFiscais\Pages;

use App\Filament\Compartilhado\AcaoImportarXml;
use App\Filament\Compartilhado\TabelaNotas;
use App\Filament\Empresa\Resources\LotesImportacao\LoteImportacaoResource;
use App\Filament\Empresa\Resources\NotasFiscais\NotasFiscaisResource;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

/** Notas de entrada e de saída em uma lista só, separadas pelas abas (RN-740). */
class ListNotasFiscais extends ListRecords
{
    protected static string $resource = NotasFiscaisResource::class;

    /** A tabela de notas tem muitas colunas: usa a largura toda da tela. */
    protected Width|string|null $maxContentWidth = Width::Full;

    public function getSubheading(): ?string
    {
        return Filament::getTenant()->razao_social.' — notas de entrada e de saída, organizadas pela competência da emissão.';
    }

    public function getTabs(): array
    {
        return TabelaNotas::abas();
    }

    protected function getHeaderActions(): array
    {
        return [
            AcaoImportarXml::make(fn () => Filament::getTenant(), fn ($lote) => LoteImportacaoResource::getUrl('view', ['record' => $lote])),
            Action::make('envios')->label('Histórico de envios')->icon('heroicon-o-inbox-arrow-down')->color('gray')
                ->url(LoteImportacaoResource::getUrl('index')),
        ];
    }
}
