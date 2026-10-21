<?php

namespace App\Filament\Portal\Resources\Despesas\Pages;

use App\Filament\Empresa\Resources\Despesas\Pages\ManageDespesas as ManageDespesasDaEmpresa;
use App\Filament\Portal\Resources\Despesas\DespesaResource;

class ManageDespesas extends ManageDespesasDaEmpresa
{
    protected static string $resource = DespesaResource::class;
}
