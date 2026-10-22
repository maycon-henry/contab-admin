<?php

namespace App\Filament\Empresa\Resources\Funcionarios\Pages;

use App\Filament\Compartilhado\TabelaDocumentosFuncionario;
use App\Filament\Empresa\Resources\Funcionarios\CadastroFuncionarioResource;
use App\Filament\Empresa\Resources\Funcionarios\FuncionarioResource;
use App\Models\Funcionario;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Table;

/** Documentos do funcionário, de todos os contratos — regras.md RN-569 e RN-573. */
class DocumentosFuncionario extends ManageRelatedRecords
{
    protected static string $resource = FuncionarioResource::class;

    protected static string $relationship = 'documentos';

    public static function canAccess(array $parameters = []): bool
    {
        return FuncionarioResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'Documentos — '.$this->getFuncionario()->nome;
    }

    public function getBreadcrumbs(): array
    {
        return [FuncionarioResource::getUrl('index') => 'Funcionários', 'Documentos'];
    }

    public function table(Table $table): Table
    {
        return TabelaDocumentosFuncionario::configurar($table, escritorio: true);
    }

    protected function getHeaderActions(): array
    {
        $funcionario = $this->getFuncionario();

        return [
            TabelaDocumentosFuncionario::acaoDossie(fn () => $this->getFuncionario()),
            // avaliar e trocar documentos continua na ficha do funcionário (RN-567)
            Action::make('cadastro')->label('Abrir cadastro')->icon('heroicon-o-identification')->color('gray')
                ->visible($funcionario->contratoAtual !== null && CadastroFuncionarioResource::canViewAny())
                ->url(fn () => CadastroFuncionarioResource::getUrl('view', ['record' => $funcionario->contratoAtual])),
        ];
    }

    private function getFuncionario(): Funcionario
    {
        /** @var Funcionario */
        return $this->getRecord();
    }
}
