<?php

namespace App\Filament\Escritorio\Resources\Catalogo\Pages;

use App\Filament\Escritorio\Resources\Catalogo\TipoDocumentoResource;
use Filament\Resources\Pages\EditRecord;

class EditTipoDocumento extends EditRecord
{
    protected static string $resource = TipoDocumentoResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
