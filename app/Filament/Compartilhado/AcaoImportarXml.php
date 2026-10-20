<?php

namespace App\Filament\Compartilhado;

use App\Actions\NotasFiscais\ImportarLoteXml;
use App\Enums\TipoUsuario;
use App\Filament\Support\Acao;
use App\Models\Empresa;
use App\Models\LoteImportacaoXml;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;

/**
 * Botão "Enviar XMLs" — regras.md RN-700 a RN-703. A competência de cada nota vem do próprio XML.
 * Usado no contexto da empresa (escritório), na área de trabalho e no portal da empresa.
 */
final class AcaoImportarXml
{
    /**
     * @param  callable(): Empresa  $empresa
     * @param  callable(LoteImportacaoXml): string  $urlRelatorio
     */
    public static function make(callable $empresa, callable $urlRelatorio): Action
    {
        return Action::make('importarXml')->label('Enviar XMLs')->icon('heroicon-o-arrow-up-tray')
            ->visible(fn () => auth()->user()->tipo === TipoUsuario::UsuarioEmpresa || auth()->user()->can('notas.gerenciar'))
            ->modalHeading(fn () => 'Enviar XMLs — '.$empresa()->getFilamentName())
            ->modalDescription('Envie os XMLs (ou um .zip). O sistema identifica o mês e se cada nota é de entrada ou de saída.')
            ->schema([
                FileUpload::make('arquivos')->label('Arquivos XML ou ZIP')->multiple()->required()
                    ->disk('local')->directory('tmp/xml')->visibility('private')->storeFileNamesIn('nomes')
                    ->acceptedFileTypes(['text/xml', 'application/xml', 'application/zip', 'application/x-zip-compressed'])
                    ->maxFiles(200)->maxSize(51200),
            ])
            ->action(function (array $data, Action $action) use ($empresa, $urlRelatorio) {
                $arquivos = collect($data['arquivos'])->mapWithKeys(fn ($c) => [$c => $data['nomes'][$c] ?? basename($c)])->all();
                $lote = null;

                Acao::rodar(function () use ($empresa, $arquivos, &$lote) {
                    $lote = app(ImportarLoteXml::class)->executar($empresa(), auth()->user(), $arquivos);
                }, null, $action);

                if ($lote) {
                    Notification::make()->success()
                        ->title("{$lote->total_arquivos} arquivo(s) recebido(s). O processamento ocorre em segundo plano.")
                        ->actions([Action::make('ver')->label('Ver resultado do envio')->url($urlRelatorio($lote))])
                        ->send();
                }
            });
    }
}
