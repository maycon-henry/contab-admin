<?php

namespace App\Filament\Empresa\Resources\Despesas;

use App\Actions\Despesas\ExcluirDespesa;
use App\Actions\Despesas\RegistrarPagamentoDespesa;
use App\Actions\Despesas\SalvarDespesa;
use App\Enums\Permissao;
use App\Filament\Compartilhado\CampoMoeda;
use App\Filament\Empresa\Resources\Despesas\Pages\ManageDespesas;
use App\Filament\Support\Acao;
use App\Models\Despesa;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

/** Despesas da empresa selecionada — regras.md seção 19. */
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
            TextInput::make('descricao')->label('Descrição')->required()->maxLength(255)->columnSpanFull(),
            CampoMoeda::make('valor')->label('Valor')->required(),
            DatePicker::make('data_vencimento')->label('Data de vencimento')->displayFormat('d/m/Y')->native(false)->required(),
            // RN-1606 — boleto da despesa
            FileUpload::make('boleto_caminho')->label('Boleto')
                ->disk(Despesa::DISCO)
                ->directory(fn () => 'escritorios/'.auth()->user()->escritorio_id.'/empresas/'.Filament::getTenant()?->getKey().'/despesas')
                ->visibility('private')
                ->storeFileNamesIn('boleto_nome')
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                ->maxSize(10240)
                ->downloadable()
                ->openable()
                ->helperText('PDF ou imagem, até 10 MB.')
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('descricao')->label('Descrição')->searchable()->sortable(),
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
            ])
            ->filters([
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
                Action::make('boleto')->label('Boleto')->icon('heroicon-o-document-arrow-down')->color('gray')
                    ->visible(fn (Despesa $record) => filled($record->boleto_caminho))
                    ->action(fn (Despesa $record) => Storage::disk(Despesa::DISCO)->download($record->boleto_caminho, $record->boleto_nome ?: 'boleto.pdf')),
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
