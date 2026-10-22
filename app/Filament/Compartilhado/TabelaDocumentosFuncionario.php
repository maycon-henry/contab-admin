<?php

namespace App\Filament\Compartilhado;

use App\Actions\Funcionarios\GerarDossieFuncionario;
use App\Enums\GrupoDocumentoFuncionario;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Documento;
use App\Models\Funcionario;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Documentos do funcionário (escritório e portal) — regras.md RN-569: todos os contratos,
 * agrupados (Pessoais, Dependentes, Admissão, Desligamento), com a situação de cada um e,
 * se pedido, as versões anteriores (RN-562). A avaliação continua na ficha da admissão.
 */
final class TabelaDocumentosFuncionario
{
    public static function configurar(Table $table, bool $escritorio): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['enviadoPor', 'avaliadoPor', 'documentavel'])
                ->orderByRaw(self::ordemDosGrupos())->orderBy('tipo')->orderByDesc('id'))
            ->columns([
                TextColumn::make('grupo')->label('Grupo')->badge()->color('gray')
                    ->state(fn (Documento $record) => $record->grupo()->getLabel()),
                TextColumn::make('tipo')->label('Documento')
                    ->formatStateUsing(fn (Documento $record) => $record->rotulo())
                    ->description(fn (Documento $record) => $record->substituido ? 'Versão anterior' : null),
                TextColumn::make('nome_original')->label('Arquivo')->searchable()
                    ->url(fn (Documento $record) => $record->urlTemporaria(), shouldOpenInNewTab: true)->color('primary')
                    ->description(fn (Documento $record) => $record->tamanho ? number_format($record->tamanho / 1024 / 1024, 1, ',', '.').' MB' : null),
                TextColumn::make('contrato')->label('Contrato')->toggleable()
                    ->state(fn (Documento $record) => ($data = $record->documentavel?->data_admissao ?? $record->documentavel?->data_admissao_prevista)
                        ? 'Admissão '.$data->format('d/m/Y') : '—'),
                TextColumn::make('created_at')->label('Enviado em')->dateTime('d/m/Y H:i')->sortable()
                    ->description(fn (Documento $record) => $escritorio
                        ? ($record->origem === 'empresa' ? 'Empresa' : 'Escritório').' — '.($record->enviadoPor?->name ?? '—')
                        : ($record->origem === 'empresa' ? 'Pela empresa' : 'Pelo escritório')),
                TextColumn::make('avaliacao')->label('Situação')->badge()
                    ->state(fn (Documento $record) => match ($record->avaliacao) {
                        'aceito' => 'Aceito',
                        'recusado' => 'Recusado',
                        default => 'Em análise',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'Aceito' => 'success',
                        'Recusado' => 'danger',
                        default => 'info',
                    })
                    // RN-655 — o nome de quem avaliou só aparece para o escritório
                    ->description(fn (Documento $record) => collect([
                        $record->avaliacao === 'recusado' ? $record->motivo_recusa : null,
                        $escritorio && $record->avaliado_em ? 'por '.($record->avaliadoPor?->name ?? '—').' em '.$record->avaliado_em->format('d/m/Y') : null,
                    ])->filter()->implode(' — ') ?: null),
                TextColumn::make('validade')->label('Validade')->date('d/m/Y')->placeholder('—')->toggleable()
                    ->color(fn (Documento $record) => $record->validade?->isPast() ? 'danger' : null),
            ])
            ->filters([
                SelectFilter::make('grupo')->label('Grupo')->options(GrupoDocumentoFuncionario::class)
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, function (Builder $query, $valor) {
                        $grupo = $valor instanceof GrupoDocumentoFuncionario ? $valor : GrupoDocumentoFuncionario::from($valor);

                        return match ($grupo) {
                            GrupoDocumentoFuncionario::Desligamento => $query->where('tipo', 'like', GrupoDocumentoFuncionario::PREFIXO_DESLIGAMENTO.'%'),
                            GrupoDocumentoFuncionario::Outros => $query->whereNotIn('tipo', array_merge(...array_map(fn ($g) => $g->tipos(), GrupoDocumentoFuncionario::cases())))
                                ->where('tipo', 'not like', GrupoDocumentoFuncionario::PREFIXO_DESLIGAMENTO.'%'),
                            default => $query->whereIn('tipo', $grupo->tipos()),
                        };
                    })),
                SelectFilter::make('avaliacao')->label('Situação')
                    ->options(['aceito' => 'Aceito', 'recusado' => 'Recusado', 'em_analise' => 'Em análise'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        null, '' => $query,
                        'em_analise' => $query->whereNull('avaliacao'),
                        default => $query->where('avaliacao', $data['value']),
                    }),
                // RN-562 — por padrão, só a versão vigente de cada documento
                TernaryFilter::make('versoes')->label('Versões anteriores')
                    ->placeholder('Só as vigentes')->trueLabel('Todas as versões')->falseLabel('Só as anteriores')
                    ->queries(
                        true: fn (Builder $query) => $query,
                        false: fn (Builder $query) => $query->where('substituido', true),
                        blank: fn (Builder $query) => $query->where('substituido', false),
                    ),
            ])
            ->emptyStateHeading('Nenhum documento')
            ->emptyStateDescription('Os documentos enviados na admissão e no desligamento aparecem aqui.')
            ->paginated(false);
    }

    /** RN-573 — botão "Baixar dossiê (.zip)" no topo da página. */
    public static function acaoDossie(callable $funcionario): Action
    {
        return Action::make('dossie')->label('Baixar dossiê (.zip)')->icon('heroicon-o-archive-box-arrow-down')->color('gray')
            ->action(function () use ($funcionario) {
                /** @var Funcionario $f */
                $f = $funcionario();

                try {
                    $zip = app(GerarDossieFuncionario::class)->executar($f, auth()->user());
                } catch (RegraDeNegocioException $e) {
                    Notification::make()->warning()->title($e->getMessage())->send();

                    return null;
                }

                return response()->download($zip['caminho'], $zip['nome'])->deleteFileAfterSend();
            });
    }

    /** Pessoais, Dependentes, Admissão, Desligamento, Outros — a mesma ordem das pastas do dossiê. */
    private static function ordemDosGrupos(): string
    {
        $casos = collect(GrupoDocumentoFuncionario::cases())
            ->reject(fn (GrupoDocumentoFuncionario $g) => in_array($g, [GrupoDocumentoFuncionario::Desligamento, GrupoDocumentoFuncionario::Outros], true))
            ->map(fn (GrupoDocumentoFuncionario $g) => 'WHEN tipo IN ('.collect($g->tipos())->map(fn ($t) => "'{$t}'")->implode(',').") THEN {$g->ordem()}")
            ->implode(' ');

        return "CASE {$casos} WHEN tipo LIKE '".GrupoDocumentoFuncionario::PREFIXO_DESLIGAMENTO."%' THEN "
            .GrupoDocumentoFuncionario::Desligamento->ordem().' ELSE '.GrupoDocumentoFuncionario::Outros->ordem().' END';
    }
}
