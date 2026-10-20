<?php

namespace App\Filament\Empresa\Resources\NotasFiscais;

use App\Actions\NotasFiscais\MoverNotaCompetencia;
use App\Domain\Competencia as Mes;
use App\Enums\ClassificacaoNota;
use App\Enums\Permissao;
use App\Filament\Compartilhado\TabelaNotas;
use App\Filament\Support\Acao;
use App\Models\NotaFiscal;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Base das notas fiscais da empresa selecionada — regras.md seção 10.
 * Entrada e saída são telas separadas, cada uma restrita às suas classificações (RN-716).
 */
abstract class NotasFiscaisResource extends Resource
{
    protected static ?string $model = NotaFiscal::class;

    protected static ?string $tenantRelationshipName = 'notasFiscais';

    protected static ?string $modelLabel = 'nota fiscal';

    /** @return array<ClassificacaoNota> */
    abstract public static function classificacoes(): array;

    public static function canViewAny(): bool
    {
        return auth()->user()->can(Permissao::NotasVisualizar->value);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('classificacao', static::classificacoes());
    }

    public static function table(Table $table): Table
    {
        $tabela = TabelaNotas::configurar($table, mostrarCompetencia: true);

        return $tabela
            ->filters([
                SelectFilter::make('competencia')->label('Competência')
                    ->options(fn () => NotaFiscal::query()->where('empresa_id', Filament::getTenant()?->getKey())
                        ->whereIn('classificacao', static::classificacoes())
                        ->select('competencia')->distinct()->orderByDesc('competencia')->pluck('competencia')
                        ->mapWithKeys(fn ($c) => [$c->toDateString() => $c->format('m/Y')])),
                ...collect($tabela->getFilters())->reject(fn ($f, $nome) => $nome === 'classificacao' && count(static::classificacoes()) === 1)->all(),
            ])
            ->recordActions([
                ...$tabela->getRecordActions(),
                // RN-722 — só o escritório move nota de competência, com justificativa
                Action::make('mover')->label('Mover de mês')->icon('heroicon-o-arrows-right-left')->color('gray')
                    ->visible(fn () => auth()->user()->can(Permissao::NotasGerenciar->value))
                    ->schema([
                        TextInput::make('competencia')->label('Nova competência (MM/AAAA)')->required()->mask('99/9999'),
                        TextInput::make('justificativa')->label('Justificativa')->required(),
                    ])
                    ->action(fn (NotaFiscal $record, array $data, Action $action) => Acao::rodar(
                        fn () => app(MoverNotaCompetencia::class)->executar($record, auth()->user(), Mes::parse($data['competencia']), $data['justificativa']),
                        'Nota movida de competência.', $action)),
            ]);
    }
}
