<?php

namespace App\Filament\Escritorio\Acoes;

use App\Actions\Itens\AceitarItem;
use App\Actions\Itens\ConcluirTarefa;
use App\Actions\Itens\DispensarItem;
use App\Actions\Itens\RecusarItem;
use App\Enums\MotivoRecusa;
use App\Enums\Permissao;
use App\Enums\ResultadoCertidao;
use App\Enums\SituacaoItem;
use App\Enums\TipoItem;
use App\Filament\Support\Acao;
use App\Filament\Support\TabelaInformativa;
use App\Models\Documento;
use App\Models\ItemCompetencia;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Text;
use Illuminate\Support\HtmlString;

/** Ações sobre itens da competência, usadas na fila de avaliação e na área de trabalho (regras.md seção 9.5). */
final class AcoesItem
{
    public static function todas(): array
    {
        return [self::verArquivos(), self::aceitar(), self::recusar(), self::concluirTarefa(), self::dispensar()];
    }

    public static function aceitar(): Action
    {
        return Action::make('aceitar')
            ->label(fn (ItemCompetencia $record) => $record->sem_movimento ? 'Confirmar sem movimento' : 'Aceitar')
            ->icon('heroicon-o-check-circle')->color('success')
            ->visible(fn (ItemCompetencia $record) => $record->situacao === SituacaoItem::EmAvaliacao && auth()->user()->can(Permissao::DocumentosAvaliar->value))
            ->modalHeading(fn (ItemCompetencia $record) => "Avaliar: {$record->nome}")
            ->schema(fn (ItemCompetencia $record) => array_filter([
                $record->sem_movimento
                    ? Text::make(new HtmlString('<strong>A empresa informou "sem movimento":</strong> '.e($record->observacao_empresa)))
                    : Text::make(new HtmlString(self::linksArquivos($record))),
                $record->exige_validade && ! $record->sem_movimento
                    ? DatePicker::make('validade')->label('Data de validade (confirme ou corrija)')->default($record->validade)->required()->minDate(today())
                    : null,
                $record->eh_certidao && ! $record->sem_movimento
                    ? Select::make('resultado_certidao')->label('Resultado da certidão')->options(ResultadoCertidao::class)->required()
                        ->helperText('Certidão positiva é aceita, mas gera alerta ao escritório (RN-653).')
                    : null,
            ]))
            ->action(fn (ItemCompetencia $record, array $data, Action $action) => Acao::rodar(
                fn () => app(AceitarItem::class)->executar(
                    $record, auth()->user(), $data['validade'] ?? null,
                    isset($data['resultado_certidao']) ? ResultadoCertidao::from($data['resultado_certidao'] instanceof ResultadoCertidao ? $data['resultado_certidao']->value : $data['resultado_certidao']) : null,
                ),
                'Item aceito.', $action,
            ));
    }

    public static function recusar(): Action
    {
        return Action::make('recusar')
            ->label('Recusar')->icon('heroicon-o-x-circle')->color('danger')
            ->visible(fn (ItemCompetencia $record) => $record->situacao === SituacaoItem::EmAvaliacao && auth()->user()->can(Permissao::DocumentosAvaliar->value))
            ->modalHeading(fn (ItemCompetencia $record) => "Recusar: {$record->nome}")
            ->schema(fn (ItemCompetencia $record) => [
                Text::make(new HtmlString($record->sem_movimento ? 'A empresa informou "sem movimento".' : self::linksArquivos($record))),
                Select::make('motivo')->label('Motivo')->options(MotivoRecusa::class)->required()
                    ->default($record->sem_movimento ? MotivoRecusa::SemMovimentoNaoConfirmado->value : null),
                Textarea::make('complemento')->label('Complemento (a empresa verá este texto)')->rows(3),
            ])
            ->action(fn (ItemCompetencia $record, array $data, Action $action) => Acao::rodar(
                fn () => app(RecusarItem::class)->executar(
                    $record, auth()->user(),
                    $data['motivo'] instanceof MotivoRecusa ? $data['motivo'] : MotivoRecusa::from($data['motivo']),
                    $data['complemento'] ?? null,
                ),
                'Item recusado. A empresa foi avisada.', $action,
            ));
    }

    public static function concluirTarefa(): Action
    {
        return Action::make('concluirTarefa')
            ->label('Concluir tarefa')->icon('heroicon-o-check')->color('success')
            ->visible(fn (ItemCompetencia $record) => $record->tipo === TipoItem::Tarefa && $record->situacao === SituacaoItem::Pendente)
            ->requiresConfirmation()
            ->action(fn (ItemCompetencia $record, Action $action) => Acao::rodar(fn () => app(ConcluirTarefa::class)->executar($record, auth()->user()), 'Tarefa concluída.', $action));
    }

    public static function dispensar(): Action
    {
        return Action::make('dispensar')
            ->label('Não se aplica')->icon('heroicon-o-minus-circle')->color('gray')
            ->visible(fn (ItemCompetencia $record) => in_array($record->situacao, [SituacaoItem::Pendente, SituacaoItem::Recusado], true))
            ->schema([Textarea::make('justificativa')->label('Justificativa')->required()])
            ->action(fn (ItemCompetencia $record, array $data, Action $action) => Acao::rodar(
                fn () => app(DispensarItem::class)->executar($record, auth()->user(), $data['justificativa']), 'Item marcado como "não se aplica".', $action));
    }

    public static function verArquivos(): Action
    {
        return Action::make('arquivos')
            ->label('Arquivos e histórico')->icon('heroicon-o-paper-clip')->color('gray')
            ->modalHeading(fn (ItemCompetencia $record) => $record->nome)->modalWidth('3xl')
            ->modalSubmitAction(false)->modalCancelActionLabel('Fechar')
            ->schema(fn (ItemCompetencia $record) => array_filter([
                Text::make(new HtmlString('<strong>Arquivos enviados</strong>')),
                Text::make(self::tabelaArquivos($record)),
                $record->avaliacoes()->exists() ? Text::make(new HtmlString('<strong>Avaliações</strong>')) : null,
                $record->avaliacoes()->exists() ? Text::make(self::historico($record)) : null,
            ]));
    }

    /** Arquivos do item em tabela (janela "Arquivos e histórico"). */
    private static function tabelaArquivos(ItemCompetencia $item): HtmlString
    {
        $documentos = $item->aceito_por_validade
            ? Documento::whereKey($item->documento_aproveitado_id)->get()
            : $item->documentos()->latest('id')->get();

        return TabelaInformativa::html(
            ['Arquivo', 'Enviado em', 'Validade'],
            $documentos->map(fn (Documento $d) => [
                new HtmlString('<a style="color:#0284c7;text-decoration:underline" target="_blank" href="'.e($d->urlTemporaria()).'">'.e($d->nome_original).'</a>'),
                $d->created_at->format('d/m/Y H:i'),
                $d->validade?->format('d/m/Y') ?? '—',
            ]),
            'Nenhum arquivo enviado.',
        );
    }

    public static function linksArquivos(ItemCompetencia $item): string
    {
        $documentos = $item->documentos()->latest('id')->get();

        if ($item->aceito_por_validade) {
            $documentos = Documento::whereKey($item->documento_aproveitado_id)->get();
        }

        if ($documentos->isEmpty()) {
            return '<p>Nenhum arquivo enviado.</p>';
        }

        return '<p><strong>Arquivos enviados:</strong></p><ul style="list-style:disc;padding-left:1.2rem">'
            .$documentos->map(fn (Documento $d) => '<li><a style="color:#0284c7;text-decoration:underline" target="_blank" href="'.e($d->urlTemporaria()).'">'.e($d->nome_original).'</a> — '
                .$d->created_at->format('d/m/Y H:i').($d->validade ? ' — validade '.$d->validade->format('d/m/Y') : '').'</li>')->implode('')
            .'</ul>';
    }

    /** RN-655 — o nome do avaliador só aparece para o escritório. */
    private static function historico(ItemCompetencia $item): HtmlString
    {
        $equipe = auth()->user()->ehDaEquipe();

        return TabelaInformativa::html(
            array_values(array_filter(['Data', 'Resultado', $equipe ? 'Avaliador' : null, 'Motivo'])),
            $item->avaliacoes()->with('user')->get()->map(fn ($a) => array_values(array_filter([
                $a->created_at->format('d/m/Y H:i'),
                SituacaoItem::tryFrom($a->resultado)?->getLabel() ?? $a->resultado,
                $equipe ? ($a->user?->name ?? '—') : false,
                trim(($a->motivo_recusa ? $a->motivo_recusa->getLabel() : '').($a->complemento ? ' — '.$a->complemento : ''), ' —') ?: '—',
            ], fn ($v) => $v !== false))),
        );
    }
}
