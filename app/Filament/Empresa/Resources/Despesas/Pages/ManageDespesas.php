<?php

namespace App\Filament\Empresa\Resources\Despesas\Pages;

use App\Actions\Despesas\SalvarDespesa;
use App\Filament\Empresa\Resources\Despesas\DespesaResource;
use App\Filament\Support\Acao;
use App\Models\Despesa;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ManageRecords;

class ManageDespesas extends ManageRecords
{
    protected static string $resource = DespesaResource::class;

    public function getSubheading(): ?string
    {
        return Filament::getTenant()?->razao_social;
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nova despesa')
                ->using(function (array $data, CreateAction $action): Despesa {
                    $despesa = null;
                    Acao::rodar(function () use ($data, &$despesa) {
                        $despesa = app(SalvarDespesa::class)->criar(Filament::getTenant(), auth()->user(), $data);
                    }, action: $action);

                    return $despesa;
                }),
        ];
    }
}
