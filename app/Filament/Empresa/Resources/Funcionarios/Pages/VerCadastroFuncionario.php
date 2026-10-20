<?php

namespace App\Filament\Empresa\Resources\Funcionarios\Pages;

use App\Filament\Empresa\Resources\Funcionarios\CadastroFuncionarioResource;
use App\Filament\Empresa\Resources\Funcionarios\FuncionarioResource;
use App\Filament\Escritorio\Resources\Admissoes\Pages\ViewAdmissao;

/** Ficha do funcionário no contexto da empresa (mesma tela da fila de admissões). */
class VerCadastroFuncionario extends ViewAdmissao
{
    protected static string $resource = CadastroFuncionarioResource::class;

    protected function urlPadraoDeRetorno(): string
    {
        return FuncionarioResource::getUrl('index');
    }

    public function getBreadcrumbs(): array
    {
        return [FuncionarioResource::getUrl('index') => 'Funcionários', 'Cadastro'];
    }
}
