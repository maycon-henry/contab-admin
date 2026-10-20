<?php

namespace App\Filament\Portal\Resources\NotasFiscais\Pages;

use App\Actions\NotasFiscais\ImportarLoteXml;
use App\Filament\Portal\Resources\LotesImportacao\LoteImportacaoResource;
use App\Filament\Portal\Resources\NotasFiscais\NotaFiscalResource;
use App\Filament\Support\Acao;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

/** RN-700 a RN-703 — a empresa envia os XMLs sem informar o mês */
class ListNotasFiscais extends ListRecords
{
    protected static string $resource = NotaFiscalResource::class;

    protected ?string $subheading = 'Envie os XMLs das suas notas. O sistema descobre o mês, se é entrada ou saída, e organiza tudo sozinho.';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('enviar')->label('Enviar XMLs')->icon('heroicon-o-arrow-up-tray')
                ->modalDescription('Você pode enviar vários arquivos .xml de uma vez ou um arquivo .zip. Não é preciso informar o mês.')
                ->schema([
                    FileUpload::make('arquivos')->label('Arquivos XML ou ZIP')->multiple()->required()
                        ->disk('local')->directory('tmp/xml')->visibility('private')->storeFileNamesIn('nomes')
                        ->acceptedFileTypes(['text/xml', 'application/xml', 'application/zip', 'application/x-zip-compressed'])
                        ->maxFiles(200)->maxSize(51200),
                ])
                ->action(function (array $data, Action $action) {
                    $arquivos = collect($data['arquivos'])->mapWithKeys(fn ($c) => [$c => $data['nomes'][$c] ?? basename($c)])->all();
                    $lote = null;

                    Acao::rodar(function () use ($arquivos, &$lote) {
                        $lote = app(ImportarLoteXml::class)->executar(filament()->getTenant(), auth()->user(), $arquivos);
                    }, null, $action);

                    if ($lote) {
                        Notification::make()->success()
                            ->title("{$lote->total_arquivos} arquivo(s) recebido(s). Estamos processando.")
                            ->actions([Action::make('ver')->label('Ver resultado do envio')->url(LoteImportacaoResource::getUrl('view', ['record' => $lote]))])
                            ->send();
                    }
                }),
        ];
    }
}
