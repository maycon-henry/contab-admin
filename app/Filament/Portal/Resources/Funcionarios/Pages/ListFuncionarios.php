<?php

namespace App\Filament\Portal\Resources\Funcionarios\Pages;

use App\Filament\Portal\Resources\Admissoes\AdmissaoResource;
use App\Filament\Portal\Resources\Funcionarios\FuncionarioResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListFuncionarios extends ListRecords
{
    protected static string $resource = FuncionarioResource::class;

    protected ?string $subheading = 'Para contratar, clique em "Admitir novo", preencha os dados, anexe os documentos e solicite a admissão. O escritório faz o registro.';

    protected function getHeaderActions(): array
    {
        return [
            // RN-580 — a admissão nasce como rascunho
            Action::make('admitir')->label('Admitir novo')->icon('heroicon-o-user-plus')
                ->url(AdmissaoResource::getUrl('create')),
        ];
    }
}
