<?php

namespace App\Filament\Escritorio\Resources\Empresas\Pages;

use App\Filament\Escritorio\Resources\Empresas\EmpresaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmpresas extends ListRecords
{
    protected static string $resource = EmpresaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nova empresa')];
    }
}
