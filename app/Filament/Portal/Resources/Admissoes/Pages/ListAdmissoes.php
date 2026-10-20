<?php

namespace App\Filament\Portal\Resources\Admissoes\Pages;

use App\Filament\Portal\Resources\Admissoes\AdmissaoResource;
use App\Filament\Portal\Resources\Funcionarios\FuncionarioResource;
use Filament\Resources\Pages\ListRecords;

/** As admissões são acompanhadas pela tela de Funcionários; este endereço só redireciona para lá. */
class ListAdmissoes extends ListRecords
{
    protected static string $resource = AdmissaoResource::class;

    public function mount(): void
    {
        $this->redirect(FuncionarioResource::getUrl('index'));
    }
}
