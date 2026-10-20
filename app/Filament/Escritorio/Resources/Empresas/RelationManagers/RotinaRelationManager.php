<?php

namespace App\Filament\Escritorio\Resources\Empresas\RelationManagers;

use App\Domain\Rotina\CalculadoraRotinaMensal;
use App\Domain\Rotina\ItemDaRotina;
use App\Enums\Permissao;
use App\Models\EmpresaRotinaAjuste;
use App\Models\TipoDocumento;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * regras.md RN-615 a RN-618 — rotina mensal vigente da empresa, com ajustes.
 * A lista vem da CalculadoraRotinaMensal (única fonte da regra).
 */
class RotinaRelationManager extends RelationManager
{
    protected static string $relationship = 'ajustesRotina';

    protected static ?string $title = 'Rotina mensal';

    public function table(Table $table): Table
    {
        $empresa = $this->getOwnerRecord();
        $rotina = app(CalculadoraRotinaMensal::class)->calcular($empresa, incluirDispensados: true)->keyBy(fn (ItemDaRotina $i) => $i->tipo->id);

        return $table
            ->query(fn () => TipoDocumento::query()->whereIn('id', $rotina->keys()->all() ?: [0]))
            ->heading('Rotina mensal da empresa')
            ->description('Documentos e tarefas do catálogo que se aplicam a esta empresa. Ajustes valem para as próximas competências (RN-612).')
            ->columns([
                TextColumn::make('nome')->label('Item'),
                TextColumn::make('tipo')->label('Tipo')->badge()->formatStateUsing(fn ($state) => $state->value === 'documento' ? 'Documento' : 'Tarefa'),
                TextColumn::make('origem')->label('Origem')->state(fn (TipoDocumento $record) => $rotina[$record->id]->origem()),
                TextColumn::make('prazo')->label('Prazo')->state(fn (TipoDocumento $record) => 'Dia '.$rotina[$record->id]->prazoDia.' do mês seguinte'),
                IconColumn::make('obrigatorio_efetivo')->label('Obrigatório')->boolean()->state(fn (TipoDocumento $record) => $rotina[$record->id]->obrigatorio),
                TextColumn::make('ajuste')->label('Ajuste')->badge()->color(fn ($state) => $state === 'Dispensado' ? 'danger' : 'warning')
                    ->state(fn (TipoDocumento $record) => $rotina[$record->id]->dispensado ? 'Dispensado' : ($rotina[$record->id]->ajustado ? 'Ajustado' : null))
                    ->tooltip(fn (TipoDocumento $record) => $rotina[$record->id]->justificativaDispensa),
            ])
            ->recordActions([
                Action::make('ajustar')->label('Ajustar')->icon('heroicon-o-adjustments-horizontal')
                    ->visible(fn () => auth()->user()->can(Permissao::EmpresasGerenciar->value))
                    ->fillForm(function (TipoDocumento $record) use ($empresa) {
                        $ajuste = EmpresaRotinaAjuste::where('empresa_id', $empresa->id)->where('tipo_documento_id', $record->id)->first();

                        return [
                            'dispensado' => $ajuste?->dispensado ?? false,
                            'justificativa' => $ajuste?->justificativa,
                            'prazo_dia' => $ajuste?->prazo_dia,
                            'obrigatorio' => $ajuste?->obrigatorio === null ? 'padrao' : ($ajuste->obrigatorio ? 'sim' : 'nao'),
                        ];
                    })
                    ->schema([
                        Toggle::make('dispensado')->label('Dispensar para esta empresa')->live(),
                        TextInput::make('justificativa')->label('Justificativa')->required(fn (Get $get) => $get('dispensado')),
                        TextInput::make('prazo_dia')->label('Prazo próprio (dia do mês seguinte)')->numeric()->minValue(1)->maxValue(31)->helperText('Vazio = prazo do catálogo'),
                        Select::make('obrigatorio')->label('Obrigatoriedade')->options(['padrao' => 'Igual ao catálogo', 'sim' => 'Obrigatório', 'nao' => 'Opcional'])->default('padrao'),
                    ])
                    ->action(function (TipoDocumento $record, array $data) use ($empresa) {
                        $obrigatorio = match ($data['obrigatorio']) {
                            'sim' => true, 'nao' => false, default => null
                        };
                        $semAjuste = ! $data['dispensado'] && blank($data['prazo_dia']) && $obrigatorio === null;

                        if ($semAjuste) {
                            EmpresaRotinaAjuste::where('empresa_id', $empresa->id)->where('tipo_documento_id', $record->id)->delete();
                        } else {
                            EmpresaRotinaAjuste::updateOrCreate(
                                ['empresa_id' => $empresa->id, 'tipo_documento_id' => $record->id],
                                ['dispensado' => $data['dispensado'], 'justificativa' => $data['justificativa'], 'prazo_dia' => $data['prazo_dia'] ?: null, 'obrigatorio' => $obrigatorio],
                            );
                        }

                        Notification::make()->success()->title('Rotina ajustada. Vale a partir das próximas competências.')->send();
                        $this->redirect(request()->header('Referer') ?? url()->current());
                    }),
            ])
            ->paginated(false);
    }

    public static function canViewForRecord($ownerRecord, string $pageClass): bool
    {
        return true;
    }
}
