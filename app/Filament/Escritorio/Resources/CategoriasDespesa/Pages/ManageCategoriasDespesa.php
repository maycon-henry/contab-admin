<?php

namespace App\Filament\Escritorio\Resources\CategoriasDespesa\Pages;

use App\Actions\Despesas\SalvarCategoriaDespesa;
use App\Filament\Escritorio\Resources\CategoriasDespesa\CategoriaDespesaResource;
use App\Filament\Support\Acao;
use App\Models\CategoriaDespesa;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCategoriasDespesa extends ManageRecords
{
    protected static string $resource = CategoriaDespesaResource::class;

    protected ?string $subheading = 'Cada despesa das empresas pertence a uma destas categorias.';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nova categoria')
                ->using(function (array $data, CreateAction $action): CategoriaDespesa {
                    $categoria = null;
                    Acao::rodar(function () use ($data, &$categoria) {
                        $categoria = app(SalvarCategoriaDespesa::class)->criar(auth()->user(), $data['nome']);
                    }, action: $action);

                    return $categoria;
                }),
        ];
    }
}
