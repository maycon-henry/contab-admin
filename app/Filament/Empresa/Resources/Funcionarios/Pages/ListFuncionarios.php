<?php

namespace App\Filament\Empresa\Resources\Funcionarios\Pages;

use App\Enums\Permissao;
use App\Filament\Empresa\Resources\Funcionarios\FuncionarioResource;
use App\Filament\Escritorio\Resources\Admissoes\AdmissaoResource;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;

class ListFuncionarios extends ListRecords
{
    protected static string $resource = FuncionarioResource::class;

    public function getSubheading(): ?string
    {
        return Filament::getTenant()->razao_social;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('novaAdmissao')->label('Iniciar admissão')->icon('heroicon-o-user-plus')
                ->visible(fn () => auth()->user()->can(Permissao::AdmissoesAnalisar->value))
                ->url(fn () => AdmissaoResource::getUrl('create', ['empresa' => Filament::getTenant()->getKey()], panel: 'escritorio')),
        ];
    }
}
