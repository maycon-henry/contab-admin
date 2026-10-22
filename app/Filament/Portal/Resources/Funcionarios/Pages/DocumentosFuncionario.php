<?php

namespace App\Filament\Portal\Resources\Funcionarios\Pages;

use App\Filament\Compartilhado\TabelaDocumentosFuncionario;
use App\Filament\Portal\Resources\Admissoes\AdmissaoResource;
use App\Filament\Portal\Resources\Funcionarios\FuncionarioResource;
use App\Models\Funcionario;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Table;

/**
 * Portal — documentos do funcionário, de todos os contratos (regras.md RN-569 e RN-573).
 * O funcionário vem do tenant (empresa vinculada); a visibilidade segue RN-904.
 */
class DocumentosFuncionario extends ManageRelatedRecords
{
    protected static string $resource = FuncionarioResource::class;

    protected static string $relationship = 'documentos';

    public static function canAccess(array $parameters = []): bool
    {
        return true;
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
        return TabelaDocumentosFuncionario::configurar($table, escritorio: false);
    }

    protected function getHeaderActions(): array
    {
        $contrato = $this->getFuncionario()->contratoAtual;

        return [
            TabelaDocumentosFuncionario::acaoDossie(fn () => $this->getFuncionario()),
            // RN-565 — funcionário admitido: a empresa troca ou inclui documentos
            Action::make('atualizar')->label('Trocar documentos')->icon('heroicon-o-arrow-up-tray')
                ->visible($contrato !== null && AdmissaoResource::podeAtualizarDocumentos($contrato))
                ->url(fn () => AdmissaoResource::getUrl('documentos', ['record' => $contrato])),
        ];
    }

    private function getFuncionario(): Funcionario
    {
        /** @var Funcionario */
        return $this->getRecord();
    }
}
