<?php

namespace App\Filament\Escritorio\Resources\Equipe\Pages;

use App\Filament\Escritorio\Resources\Equipe\UsuarioResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUsuarios extends ListRecords
{
    protected static string $resource = UsuarioResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Convidar pessoa')];
    }
}
