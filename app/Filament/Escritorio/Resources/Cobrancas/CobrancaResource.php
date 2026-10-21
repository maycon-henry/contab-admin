<?php

namespace App\Filament\Escritorio\Resources\Cobrancas;

use App\Enums\Permissao;
use App\Filament\Escritorio\Resources\Cobrancas\Pages\ListCobrancas;
use App\Filament\Support\TabelaInformativa;
use App\Models\CobrancaDocumentacao;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** regras.md RN-1319 — histórico das cobranças enviadas */
class CobrancaResource extends Resource
{
    protected static ?string $model = CobrancaDocumentacao::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|UnitEnum|null $navigationGroup = 'Rotina mensal';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Cobranças enviadas';

    protected static ?string $pluralModelLabel = 'cobranças enviadas';

    protected static ?string $slug = 'cobrancas';

    public static function canViewAny(): bool
    {
        return auth()->user()->can(Permissao::CobrancaGerenciar->value);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        $resultados = ['enviada' => 'Enviada', 'sem_destinatario' => 'Não enviada: empresa sem usuário'];

        return $table
            ->columns([
                TextColumn::make('data_referencia')->label('Dia')->date('d/m/Y')->sortable(),
                TextColumn::make('empresa.razao_social')->label('Empresa')->searchable(),
                TextColumn::make('tipo')->label('Tipo')->formatStateUsing(fn ($state) => str_starts_with($state, 'manual') ? 'Manual' : 'Automática'),
                TextColumn::make('competencia_referencia')->label('Competência')->date('m/Y'),
                TextColumn::make('itens')->label('Itens cobrados')->state(fn (CobrancaDocumentacao $r) => count($r->itens)),
                TextColumn::make('destinatarios')->label('Destinatários')->state(fn (CobrancaDocumentacao $r) => implode(', ', $r->destinatarios) ?: '—')->limit(40),
                TextColumn::make('resultado')->label('Resultado')->badge()->formatStateUsing(fn ($state) => $resultados[$state] ?? $state)
                    ->color(fn ($state) => $state === 'enviada' ? 'success' : 'warning'),
            ])
            ->filters([
                SelectFilter::make('empresa_id')->label('Empresa')->relationship('empresa', 'razao_social')->searchable()->preload(),
                SelectFilter::make('resultado')->options($resultados),
            ])
            ->recordActions([
                Action::make('detalhe')->label('Itens')->icon('heroicon-o-list-bullet')->color('gray')
                    ->modalSubmitAction(false)->modalCancelActionLabel('Fechar')
                    ->modalHeading(fn (CobrancaDocumentacao $r) => 'Itens cobrados — '.$r->empresa?->razao_social)->modalWidth('3xl')
                    ->schema(fn (CobrancaDocumentacao $r) => [Text::make(TabelaInformativa::html(
                        ['Documento', 'Competência', 'Prazo', 'Situação'],
                        collect($r->itens)->map(fn ($i) => [
                            $i['nome'], $i['competencia'], $i['prazo'],
                            match (true) {
                                (bool) $i['motivo_recusa'] => TabelaInformativa::alerta('Recusado: '.$i['motivo_recusa']),
                                (bool) $i['dias_atraso'] => TabelaInformativa::alerta("Atrasado há {$i['dias_atraso']} dia(s)"),
                                default => 'Pendente',
                            },
                        ]),
                        'Nenhum item nesta cobrança.',
                    ))]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListCobrancas::route('/')];
    }
}
