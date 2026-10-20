<?php

namespace App\Filament\Escritorio\Widgets;

use App\Domain\Competencia as Mes;
use App\Domain\Consultas\ConsultaEmpresasDevendoDocumentacao;
use App\Enums\Permissao;
use App\Models\Empresa;
use App\Models\ItemCompetencia;
use App\Models\TipoDocumento;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Schemas\Components\Text;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\HtmlString;

/** regras.md RN-1512 a RN-1514 — lista de empresas devendo documentação */
class EmpresasDevendoDocumentacao extends TableWidget
{
    protected static ?int $sort = 2;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Empresas devendo documentação';

    public static function canView(): bool
    {
        return auth()->user()->can(Permissao::DashboardAdministrador->value);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => app(ConsultaEmpresasDevendoDocumentacao::class)->query(
                $this->tableFilters['competencia']['value'] ?? null,
                isset($this->tableFilters['tipo']['value']) ? (int) $this->tableFilters['tipo']['value'] ?: null : null,
                (bool) ($this->tableFilters['obrigatorios']['isActive'] ?? false),
            ))
            ->columns([
                TextColumn::make('razao_social')->label('Empresa')->weight('medium')->searchable(),
                TextColumn::make('documentos_atrasados')->label('Em atraso')->alignCenter()->color('danger')
                    ->description(fn ($record) => "{$record->obrigatorios_atrasados} obrigatório(s)"),
                TextColumn::make('recusados')->label('Recusados')->alignCenter(),
                TextColumn::make('competencia_mais_antiga')->label('Desde')->formatStateUsing(fn ($state) => Carbon::parse($state)->format('m/Y')),
                TextColumn::make('prazo_mais_antigo')->label('Maior atraso')
                    ->formatStateUsing(fn ($state) => (int) Carbon::parse($state)->diffInDays(today()).' dias'),
            ])
            ->defaultSort(fn ($query) => $query->orderBy('atrasos.prazo_mais_antigo'))
            ->filters([
                SelectFilter::make('competencia')->label('Competência')
                    ->options(fn () => collect(range(0, 11))->mapWithKeys(function ($i) {
                        $mes = Mes::atual();
                        for ($j = 0; $j < $i; $j++) {
                            $mes = $mes->anterior();
                        }

                        return [$mes->paraBanco() => $mes->paraTela()];
                    }))
                    ->query(fn ($query) => $query),
                SelectFilter::make('tipo')->label('Tipo de documento')->options(fn () => TipoDocumento::orderBy('nome')->pluck('nome', 'id'))->query(fn ($query) => $query),
                Filter::make('obrigatorios')->label('Somente obrigatórios')->toggle()->query(fn ($query) => $query),
            ])
            ->recordActions([
                // RN-1513 — expandir para ver quais documentos
                Action::make('quais')->label('Quais documentos')->icon('heroicon-o-list-bullet')->color('gray')
                    ->modalSubmitAction(false)->modalCancelActionLabel('Fechar')
                    ->modalHeading(fn ($record) => $record->razao_social)
                    ->schema(fn ($record) => [Text::make(new HtmlString($this->listaDocumentos($record)))]),
            ])
            ->emptyStateHeading('Nenhuma empresa com documentação em atraso')
            ->emptyStateIcon('heroicon-o-check-circle');
    }

    private function listaDocumentos(Empresa $empresa): string
    {
        $itens = ItemCompetencia::with('competencia')->where('empresa_id', $empresa->id)->atrasados()
            ->whereHas('competencia', fn ($q) => $q->where('situacao', 'aberta'))->orderBy('prazo')->get();

        return '<table style="width:100%;font-size:.875rem"><tr><th align="left">Documento</th><th align="left">Competência</th><th align="left">Prazo</th><th align="left">Situação</th></tr>'
            .$itens->map(fn (ItemCompetencia $i) => '<tr><td>'.e($i->nome).'</td><td>'.e($i->competencia->rotulo).'</td><td>'.$i->prazo->format('d/m/Y')
                .'</td><td>'.e($i->situacao->getLabel()).' — '.$i->diasDeAtraso().' dia(s)</td></tr>')->implode('')
            .'</table>';
    }
}
