<?php

namespace App\Filament\Portal\Resources\DocumentosDoMes;

use App\Actions\Itens\EnviarDocumentosDoItem;
use App\Actions\Itens\InformarSemMovimento;
use App\Domain\Competencia as Mes;
use App\Domain\Opcoes;
use App\Enums\SituacaoItem;
use App\Filament\Escritorio\Acoes\AcoesItem;
use App\Filament\Portal\Resources\DocumentosDoMes\Pages\ListDocumentosDoMes;
use App\Filament\Support\Acao;
use App\Models\ItemCompetencia;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

/** Portal — documentos do mês que a empresa deve entregar (regras.md seção 9.7) */
class DocumentoDoMesResource extends Resource
{
    protected static ?string $model = ItemCompetencia::class;

    protected static ?string $tenantRelationshipName = 'itensCompetencia';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentArrowUp;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Documentos do mês';

    protected static ?string $modelLabel = 'documento';

    protected static ?string $pluralModelLabel = 'Documentos do mês';

    protected static ?string $slug = 'documentos-do-mes';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $total = ItemCompetencia::somenteDocumentos()->whereIn('situacao', SituacaoItem::cobraveis())
            ->where('empresa_id', filament()->getTenant()?->getKey())->count();

        return $total ? (string) $total : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getEloquentQuery(): Builder
    {
        // RN-640 — a empresa vê apenas itens do tipo documento
        return parent::getEloquentQuery()->somenteDocumentos()->with('competencia', 'tipoDocumento');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')->label('Documento')->weight('medium')
                    ->description(fn (ItemCompetencia $r) => $r->tipoDocumento?->instrucoes)->wrap(),
                TextColumn::make('competencia.competencia')->label('Competência')->date('m/Y'),
                TextColumn::make('prazo')->label('Prazo')->date('d/m/Y')
                    ->color(fn (ItemCompetencia $r) => $r->estaAtrasado() ? 'danger' : null)
                    ->description(fn (ItemCompetencia $r) => $r->estaAtrasado() ? "Atrasado há {$r->diasDeAtraso()} dia(s)" : null),
                TextColumn::make('situacao')->label('Situação')->badge()
                    ->description(fn (ItemCompetencia $r) => $r->situacao === SituacaoItem::Recusado
                        ? 'Motivo: '.$r->motivo_recusa?->getLabel().($r->complemento_recusa ? ' — '.$r->complemento_recusa : '')
                        : ($r->aceito_por_validade ? 'Documento anterior ainda válido' : null))
                    ->wrap(),
            ])
            ->filters([
                // RN-640 — mesma lógica de competência: padrão é o mês anterior (D11)
                SelectFilter::make('competencia')->label('Competência')
                    ->options(fn () => self::competenciasDisponiveis())
                    ->default(Mes::padrao()->paraBanco())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn ($q, $v) => $q->whereHas('competencia', fn ($q) => $q->whereDate('competencia', $v)))),
                SelectFilter::make('situacao')->label('Situação')->options([
                    SituacaoItem::Pendente->value => 'Pendente', SituacaoItem::Recusado->value => 'Recusado',
                    SituacaoItem::EmAvaliacao->value => 'Em avaliação', SituacaoItem::Aceito->value => 'Aceito', SituacaoItem::NaoSeAplica->value => 'Não se aplica',
                ]),
            ])
            ->recordActions([
                self::acaoEnviar(),
                self::acaoSemMovimento(),
                AcoesItem::verArquivos(),
            ])
            ->defaultSort('prazo');
    }

    public static function acaoEnviar(): Action
    {
        return Action::make('enviar')->label(fn (ItemCompetencia $r) => $r->situacao === SituacaoItem::Recusado ? 'Reenviar' : 'Enviar')
            ->icon('heroicon-o-arrow-up-tray')->button()
            ->visible(fn (ItemCompetencia $r) => in_array($r->situacao, SituacaoItem::cobraveis(), true) && ! $r->competencia->estaFechada())
            ->modalHeading(fn (ItemCompetencia $r) => "Enviar: {$r->nome} ({$r->competencia->rotulo})")
            ->schema(fn (ItemCompetencia $r) => array_filter([
                $r->tipoDocumento?->instrucoes ? Text::make(new HtmlString('<strong>Instruções:</strong> '.e($r->tipoDocumento->instrucoes))) : null,
                FileUpload::make('arquivos')->label('Arquivo(s)')->required()
                    ->multiple($r->tipoDocumento?->multiplos_arquivos ?? false)
                    ->disk('local')->directory("escritorios/{$r->escritorio_id}/empresas/{$r->empresa_id}/documentos")->visibility('private')
                    ->storeFileNamesIn('nomes')
                    ->acceptedFileTypes(Opcoes::mimes($r->tipoDocumento?->formatos ?? []))
                    ->maxSize(20480),
                $r->exige_validade ? DatePicker::make('validade')->label('Data de validade do documento')->required()->minDate(today()) : null,
            ]))
            ->action(function (ItemCompetencia $r, array $data, Action $action) {
                $caminhos = (array) $data['arquivos'];
                $nomes = (array) ($data['nomes'] ?? []);
                $arquivos = collect($caminhos)->mapWithKeys(fn ($c) => [$c => $nomes[$c] ?? basename($c)])->all();

                Acao::rodar(fn () => app(EnviarDocumentosDoItem::class)->executar($r, auth()->user(), $arquivos, $data['validade'] ?? null),
                    'Documento enviado! O escritório vai conferir.', $action);
            });
    }

    public static function acaoSemMovimento(): Action
    {
        return Action::make('semMovimento')->label('Sem movimento')->icon('heroicon-o-minus-circle')->color('gray')
            ->visible(fn (ItemCompetencia $r) => in_array($r->situacao, SituacaoItem::cobraveis(), true) && ! $r->competencia->estaFechada())
            ->modalDescription('Informe por que não há documento neste mês. O escritório vai confirmar (RN-642).')
            ->schema([Textarea::make('observacao')->label('Observação')->required()->placeholder('Não houve movimentação bancária no mês.')])
            ->action(fn (ItemCompetencia $r, array $data, Action $action) => Acao::rodar(
                fn () => app(InformarSemMovimento::class)->executar($r, auth()->user(), $data['observacao']), 'Informação enviada ao escritório.', $action));
    }

    /** @return array<string, string> */
    public static function competenciasDisponiveis(): array
    {
        $empresa = filament()->getTenant();
        $mes = Mes::atual();
        $opcoes = [];

        while ($empresa && $mes->primeiroDia()->gte($empresa->competenciaInicial()->primeiroDia()) && count($opcoes) < 36) {
            $opcoes[$mes->paraBanco()] = $mes->paraTela();
            $mes = $mes->anterior();
        }

        return $opcoes;
    }

    public static function getPages(): array
    {
        return ['index' => ListDocumentosDoMes::route('/')];
    }
}
