<?php

namespace App\Filament\Compartilhado;

use App\Actions\Itens\EnviarDocumentosDoItem;
use App\Domain\Opcoes;
use App\Enums\Permissao;
use App\Enums\ResultadoCertidao;
use App\Enums\SituacaoItem;
use App\Filament\Support\Acao;
use App\Models\ItemCompetencia;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Text;
use Illuminate\Support\HtmlString;

/**
 * Botão "Enviar" de um documento da competência — regras.md RN-641, RN-643 e RN-646.
 * No portal, o envio vai para avaliação; pelo escritório, o documento já é aceito.
 */
final class AcaoEnviarDocumentoDoItem
{
    /**
     * @param  callable(mixed): ItemCompetencia  $item  resolve o item a partir do registro da tabela
     */
    public static function make(callable $item): Action
    {
        return Action::make('enviar')
            ->label(fn ($record) => $item($record)->situacao === SituacaoItem::Recusado ? 'Reenviar' : 'Enviar')
            ->icon('heroicon-o-arrow-up-tray')->button()
            ->visible(fn ($record) => self::podeEnviar($item($record)))
            ->modalHeading(fn ($record) => "Enviar: {$item($record)->nome} ({$item($record)->competencia->rotulo})")
            ->modalDescription(fn () => auth()->user()->ehDaEquipe() ? 'Enviado pelo escritório, o documento já fica aceito, sem passar pela avaliação.' : null)
            ->schema(function ($record) use ($item) {
                $r = $item($record);
                $peloEscritorio = auth()->user()->ehDaEquipe();

                return array_filter([
                    $r->tipoDocumento?->instrucoes ? Text::make(new HtmlString('<strong>Instruções:</strong> '.e($r->tipoDocumento->instrucoes))) : null,
                    FileUpload::make('arquivos')->label('Arquivo(s)')->required()
                        ->multiple($r->tipoDocumento?->multiplos_arquivos ?? false)
                        ->disk('local')->directory("escritorios/{$r->escritorio_id}/empresas/{$r->empresa_id}/documentos")->visibility('private')
                        ->storeFileNamesIn('nomes')
                        ->acceptedFileTypes(Opcoes::mimes($r->tipoDocumento?->formatos ?? []))
                        ->maxSize(20480),
                    $r->exige_validade ? DatePicker::make('validade')->label('Data de validade do documento')->required()->minDate(today()) : null,
                    $peloEscritorio && $r->eh_certidao
                        ? Select::make('resultado_certidao')->label('Resultado da certidão')->options(ResultadoCertidao::class)->required()
                            ->helperText('Certidão positiva é aceita, mas gera alerta ao escritório (RN-653).')
                        : null,
                ]);
            })
            ->action(function ($record, array $data, Action $action) use ($item) {
                $r = $item($record);
                $nomes = (array) ($data['nomes'] ?? []);
                $arquivos = collect((array) $data['arquivos'])->mapWithKeys(fn ($c) => [$c => $nomes[$c] ?? basename($c)])->all();
                $certidao = $data['resultado_certidao'] ?? null;
                $peloEscritorio = auth()->user()->ehDaEquipe();

                Acao::rodar(fn () => app(EnviarDocumentosDoItem::class)->executar(
                    $r, auth()->user(), $arquivos, $data['validade'] ?? null,
                    $certidao === null || $certidao instanceof ResultadoCertidao ? $certidao : ResultadoCertidao::from($certidao),
                ), $peloEscritorio ? 'Documento enviado e aceito.' : 'Documento enviado! O escritório vai conferir.', $action);
            });
    }

    private static function podeEnviar(ItemCompetencia $item): bool
    {
        $user = auth()->user();

        return in_array($item->situacao, SituacaoItem::cobraveis(), true)
            && ! $item->competencia->estaFechada()
            && (! $user->ehDaEquipe() || $user->can(Permissao::DocumentosAvaliar->value));
    }
}
