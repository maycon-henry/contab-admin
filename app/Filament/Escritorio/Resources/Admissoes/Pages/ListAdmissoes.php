<?php

namespace App\Filament\Escritorio\Resources\Admissoes\Pages;

use App\Enums\SituacaoContrato;
use App\Filament\Escritorio\Resources\Admissoes\AdmissaoResource;
use App\Models\Contrato;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/** RN-590 / RN-591 — fila de admissões */
class ListAdmissoes extends ListRecords
{
    protected static string $resource = AdmissaoResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Iniciar admissão em nome da empresa')];
    }

    public function getTabs(): array
    {
        $contar = fn (callable $filtro) => $filtro(Contrato::query())->count();

        return [
            'fila' => Tab::make('Em análise')
                ->badge($contar(fn ($q) => $q->where('situacao', SituacaoContrato::EmAnalise)))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('situacao', SituacaoContrato::EmAnalise)),
            'minhas' => Tab::make('Minhas')
                ->badge($contar(fn ($q) => $q->where('situacao', SituacaoContrato::EmAnalise)->where('responsavel_id', auth()->id())))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('situacao', SituacaoContrato::EmAnalise)->where('responsavel_id', auth()->id())),
            'devolvidas' => Tab::make('Devolvidas')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('situacao', SituacaoContrato::Devolvida)),
            'rascunhos' => Tab::make('Rascunhos das empresas')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('situacao', SituacaoContrato::Rascunho)),
            'admitidos' => Tab::make('Admitidos')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('situacao', SituacaoContrato::Admitido)),
            'desligamento' => Tab::make('Em desligamento')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('situacao', SituacaoContrato::EmDesligamento)),
            'todas' => Tab::make('Todas'),
        ];
    }
}
