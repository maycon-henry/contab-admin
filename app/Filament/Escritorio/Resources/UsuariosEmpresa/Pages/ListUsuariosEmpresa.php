<?php

namespace App\Filament\Escritorio\Resources\UsuariosEmpresa\Pages;

use App\Filament\Escritorio\Resources\UsuariosEmpresa\UsuarioEmpresaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUsuariosEmpresa extends ListRecords
{
    protected static string $resource = UsuarioEmpresaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Convidar usuário')];
    }
}
