<?php

namespace App\Filament\Portal\Resources\NotasFiscais\Pages;

use App\Filament\Compartilhado\AcaoImportarXml;
use App\Filament\Compartilhado\TabelaNotas;
use App\Filament\Portal\Resources\LotesImportacao\LoteImportacaoResource;
use App\Filament\Portal\Resources\NotasFiscais\NotaFiscalResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

/** RN-700 a RN-703 — a empresa envia os XMLs sem informar o mês e acompanha o resultado de cada envio */
class ListNotasFiscais extends ListRecords
{
    protected static string $resource = NotaFiscalResource::class;

    /** A tabela de notas tem muitas colunas: usa a largura toda da tela. */
    protected Width|string|null $maxContentWidth = Width::Full;

    protected ?string $subheading = 'Envie os XMLs das suas notas. O sistema descobre o mês, se é entrada ou saída, e organiza tudo sozinho.';

    public function getTabs(): array
    {
        return TabelaNotas::abas();
    }

    protected function getHeaderActions(): array
    {
        return [
            AcaoImportarXml::make(fn () => filament()->getTenant(), fn ($lote) => LoteImportacaoResource::getUrl('view', ['record' => $lote])),
            Action::make('historico')->label('Histórico de envios')->icon('heroicon-o-inbox-arrow-down')->color('gray')
                ->url(LoteImportacaoResource::getUrl('index')),
        ];
    }
}
