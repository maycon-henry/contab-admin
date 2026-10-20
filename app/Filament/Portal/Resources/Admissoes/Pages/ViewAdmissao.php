<?php

namespace App\Filament\Portal\Resources\Admissoes\Pages;

use App\Filament\Portal\Resources\Admissoes\AdmissaoResource;
use App\Filament\Portal\Resources\Funcionarios\FuncionarioResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/** Portal — cadastro do funcionário (somente leitura). */
class ViewAdmissao extends ViewRecord
{
    protected static string $resource = AdmissaoResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->funcionario->nome;
    }

    public function getBreadcrumbs(): array
    {
        return [FuncionarioResource::getUrl('index') => 'Funcionários', 'Cadastro'];
    }

    protected function getHeaderActions(): array
    {
        $contrato = $this->getRecord();

        return [
            Action::make('voltar')->label('Voltar')->icon('heroicon-o-arrow-left')->color('gray')->url(FuncionarioResource::getUrl('index')),
            EditAction::make()->label('Continuar admissão')->icon('heroicon-o-pencil-square')->visible(AdmissaoResource::canEdit($contrato)),
            Action::make('documentos')->label('Atualizar documentos')->icon('heroicon-o-paper-clip')
                ->visible(AdmissaoResource::podeAtualizarDocumentos($contrato))
                ->url(AdmissaoResource::getUrl('documentos', ['record' => $contrato])),
        ];
    }
}
