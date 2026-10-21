<?php

namespace App\Filament\Empresa\Resources\Despesas;

use App\Actions\Despesas\ExcluirDespesa;
use App\Actions\Despesas\RegistrarPagamentoDespesa;
use App\Actions\Despesas\SalvarDespesa;
use App\Domain\Opcoes;
use App\Enums\Permissao;
use App\Enums\TipoDocumentoDespesa;
use App\Filament\Compartilhado\CampoMoeda;
use App\Filament\Empresa\Resources\Despesas\Pages\ManageDespesas;
use App\Filament\Support\Acao;
use App\Filament\Support\TabelaInformativa;
use App\Models\CategoriaDespesa;
use App\Models\ConfigDocumentoDespesa;
use App\Models\Despesa;
use App\Models\Documento;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

/** Despesas da empresa selecionada — regras.md seção 19. Reaproveitado no portal da empresa (RN-1607). */
class DespesaResource extends Resource
{
    protected static ?string $model = Despesa::class;

    protected static ?string $tenantRelationshipName = 'despesas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Despesas';

    protected static ?string $modelLabel = 'despesa';

    protected static ?string $pluralModelLabel = 'despesas';

    public static function canViewAny(): bool
    {
        return auth()->user()->can(Permissao::DespesasGerenciar->value);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('descricao')->label('Descrição')->required()->maxLength(255),
            // RN-1609 — categorias cadastradas pelo administrador (Configurações → Categorias de despesa)
            Select::make('categoria_despesa_id')->label('Categoria')->required()->searchable()->preload()
                ->options(fn (?Despesa $record) => CategoriaDespesa::query()
                    ->where(fn (Builder $query) => $query->where('ativo', true)->when($record?->categoria_despesa_id, fn ($q, $id) => $q->orWhere('id', $id)))
                    ->orderBy('nome')->pluck('nome', 'id'))
                ->helperText(fn () => CategoriaDespesa::where('ativo', true)->exists() ? null : 'Nenhuma categoria cadastrada. O administrador do escritório cadastra em Configurações → Categorias de despesa.'),
            CampoMoeda::make('valor')->label('Valor')->required(),
            DatePicker::make('data_vencimento')->label('Data de vencimento')->displayFormat('d/m/Y')->native(false)->required(),
            // RN-1608 — cadastrar despesa já paga (só no cadastro; depois, o pagamento é registrado pela ação própria)
            Toggle::make('paga')->label('Despesa já paga')->live()->hiddenOn('edit')->columnSpanFull()
                ->afterStateUpdated(function (bool $state, Get $get, Set $set) {
                    if ($state) {
                        $set('valor_pago', $get('valor'));
                        $set('data_pagamento', today()->toDateString());
                    }
                }),
            CampoMoeda::make('valor_pago')->label('Valor pago')->required()
                ->visible(fn (Get $get, string $operation) => $operation === 'create' && $get('paga')),
            DatePicker::make('data_pagamento')->label('Data do pagamento')->displayFormat('d/m/Y')->native(false)->maxDate(today())->required()
                ->visible(fn (Get $get, string $operation) => $operation === 'create' && $get('paga')),
            // RN-1606 — documentos já anexados (na alteração)
            Text::make(fn (?Despesa $record) => self::tabelaDocumentos($record))->columnSpanFull()
                ->visible(fn (?Despesa $record) => $record?->documentos()->exists() ?? false),
            // RN-1606 / RN-1610 — boleto, recibo, nota fiscal e cheque; formatos e tamanho conforme a configuração do escritório
            Repeater::make('documentos')->label(fn (string $operation) => $operation === 'edit' ? 'Anexar mais documentos' : 'Documentos')
                ->addActionLabel('Anexar documento')->defaultItems(0)->columns(2)->columnSpanFull()
                ->schema([
                    Select::make('tipo')->label('Tipo')->options(TipoDocumentoDespesa::class)->required()->live(),
                    FileUpload::make('arquivo')->label('Arquivo')->required()
                        ->disk(Documento::DISCO)
                        ->directory(fn () => 'escritorios/'.auth()->user()->escritorio_id.'/empresas/'.Filament::getTenant()?->getKey().'/despesas')
                        ->visibility('private')
                        ->storeFileNamesIn('nome')
                        ->acceptedFileTypes(fn (Get $get) => Opcoes::mimes(self::configDoTipo($get('tipo'))?->formatos ?? []))
                        ->maxSize(fn (Get $get) => (self::configDoTipo($get('tipo'))?->tamanho_maximo_mb ?? TipoDocumentoDespesa::TAMANHO_PADRAO_MB) * 1024)
                        ->helperText(fn (Get $get) => ($c = self::configDoTipo($get('tipo')))
                            ? collect($c->formatos)->map(fn ($f) => Opcoes::FORMATOS[$f] ?? $f)->implode(', ').", até {$c->tamanho_maximo_mb} MB."
                            : 'Escolha o tipo para ver os formatos aceitos.'),
                ]),
        ])->columns(2);
    }

    /** RN-1610 — regra de formato e tamanho do tipo escolhido, conforme o escritório. */
    private static function configDoTipo(mixed $tipo): ?ConfigDocumentoDespesa
    {
        $tipo = $tipo instanceof TipoDocumentoDespesa ? $tipo : TipoDocumentoDespesa::tryFrom((string) $tipo);

        return $tipo ? ConfigDocumentoDespesa::para(auth()->user()->escritorio_id, $tipo) : null;
    }

    public static function tabelaDocumentos(?Despesa $despesa): HtmlString
    {
        return TabelaInformativa::html(
            ['Tipo', 'Arquivo', 'Enviado em', 'Por'],
            ($despesa?->documentos()->with('enviadoPor')->latest('id')->get() ?? collect())->map(fn (Documento $d) => [
                TipoDocumentoDespesa::tryFrom((string) $d->tipo)?->getLabel() ?? $d->tipo,
                new HtmlString('<a style="color:#0284c7;text-decoration:underline" target="_blank" href="'.e($d->urlTemporaria()).'">'.e($d->nome_original).'</a>'),
                $d->created_at->format('d/m/Y H:i'),
                $d->enviadoPor?->name ?? '—',
            ]),
            'Nenhum documento anexado.',
        );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('descricao')->label('Descrição')->searchable()->sortable(),
                TextColumn::make('categoria.nome')->label('Categoria')->badge()->color('gray')->placeholder('—')->sortable(),
                TextColumn::make('valor')->label('Valor')->money('BRL')->sortable()
                    ->summarize(Sum::make()->label('Total')->money('BRL')),
                TextColumn::make('data_vencimento')->label('Vencimento')->date('d/m/Y')->sortable(),
                TextColumn::make('situacao')->label('Situação')->badge()
                    ->state(fn (Despesa $record) => $record->situacao())
                    ->color(fn (string $state) => match ($state) {
                        'Paga' => 'success',
                        'Vencida' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('valor_pago')->label('Valor pago')->money('BRL')->placeholder('—')
                    ->description(fn (Despesa $record) => $record->data_pagamento ? 'em '.$record->data_pagamento->format('d/m/Y') : null)
                    ->summarize(Sum::make()->label('Total pago')->money('BRL')),
                TextColumn::make('cadastradaPor.name')->label('Cadastrada por')->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->with('categoria')->withCount('documentos'))
            ->filters([
                SelectFilter::make('categoria_despesa_id')->label('Categoria')->relationship('categoria', 'nome')->preload(),
                SelectFilter::make('situacao')->label('Situação')
                    ->options(['aberta' => 'Em aberto', 'vencida' => 'Vencida', 'paga' => 'Paga'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'paga' => $query->whereNotNull('data_pagamento'),
                        'vencida' => $query->whereNull('data_pagamento')->whereDate('data_vencimento', '<', today()),
                        'aberta' => $query->whereNull('data_pagamento')->whereDate('data_vencimento', '>=', today()),
                        default => $query,
                    }),
                Filter::make('vencimento')->label('Vencimento')
                    ->schema([
                        DatePicker::make('de')->label('Vencimento de'),
                        DatePicker::make('ate')->label('Vencimento até'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['de'] ?? null, fn ($q, $d) => $q->whereDate('data_vencimento', '>=', $d))
                        ->when($data['ate'] ?? null, fn ($q, $d) => $q->whereDate('data_vencimento', '<=', $d))),
            ])
            ->recordActions([
                // RN-1605 — registrar pagamento
                Action::make('pagar')->label('Registrar pagamento')->icon('heroicon-o-check-circle')->color('success')
                    ->visible(fn (Despesa $record) => ! $record->estaPaga())
                    ->modalHeading(fn (Despesa $record) => 'Registrar pagamento — '.$record->descricao)
                    ->modalDescription(fn (Despesa $record) => 'Valor da despesa: R$ '.number_format((float) $record->valor, 2, ',', '.')
                        .' — vencimento em '.$record->data_vencimento->format('d/m/Y'))
                    ->modalSubmitActionLabel('Registrar pagamento')
                    ->modalWidth('lg')
                    ->fillForm(fn (Despesa $record) => ['valor_pago' => $record->valor, 'data_pagamento' => today()->toDateString()])
                    ->schema([
                        CampoMoeda::make('valor_pago')->label('Valor pago')->required(),
                        DatePicker::make('data_pagamento')->label('Data do pagamento')->displayFormat('d/m/Y')->native(false)
                            ->maxDate(today())->required(),
                    ])
                    ->action(fn (Despesa $record, array $data, Action $action) => Acao::rodar(
                        fn () => app(RegistrarPagamentoDespesa::class)->executar($record, auth()->user(), $data['valor_pago'], $data['data_pagamento']),
                        'Pagamento registrado.', $action)),
                // RN-1606 — boletos, recibos, notas fiscais e cheques anexados
                Action::make('documentos')->label('Documentos')->icon('heroicon-o-paper-clip')->color('gray')
                    ->visible(fn (Despesa $record) => ($record->documentos_count ?? $record->documentos()->count()) > 0)
                    ->modalHeading(fn (Despesa $record) => 'Documentos — '.$record->descricao)->modalWidth('3xl')
                    ->modalSubmitAction(false)->modalCancelActionLabel('Fechar')
                    ->schema(fn (Despesa $record) => [Text::make(self::tabelaDocumentos($record))]),
                EditAction::make()
                    ->using(function (Despesa $record, array $data, EditAction $action) {
                        Acao::rodar(fn () => app(SalvarDespesa::class)->atualizar($record, auth()->user(), $data), action: $action);

                        return $record;
                    }),
                DeleteAction::make()
                    ->using(fn (Despesa $record, DeleteAction $action) => Acao::rodar(fn () => app(ExcluirDespesa::class)->executar($record, auth()->user()), action: $action)),
            ])
            ->emptyStateHeading('Nenhuma despesa cadastrada')
            ->defaultSort('data_vencimento');
    }

    public static function getPages(): array
    {
        return ['index' => ManageDespesas::route('/')];
    }
}
