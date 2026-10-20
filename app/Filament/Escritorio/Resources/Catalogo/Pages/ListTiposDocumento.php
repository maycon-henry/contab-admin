<?php

namespace App\Filament\Escritorio\Resources\Catalogo\Pages;

use App\Filament\Escritorio\Resources\Catalogo\TipoDocumentoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTiposDocumento extends ListRecords
{
    protected static string $resource = TipoDocumentoResource::class;

    protected ?string $subheading = 'Documentos e tarefas que o escritório exige das empresas todo mês. Alterações valem para as próximas competências.';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Novo item')];
    }
}
