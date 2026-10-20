<?php

namespace App\Filament\Escritorio\Resources\Empresas\Pages;

use App\Actions\Cobranca\EnviarCobrancaDocumentacao;
use App\Enums\Permissao;
use App\Enums\SituacaoEmpresa;
use App\Filament\Escritorio\Resources\Empresas\EmpresaResource;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditEmpresa extends EditRecord
{
    protected static string $resource = EmpresaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // RN-1321 — cobrança manual
            Action::make('cobrarAgora')->label('Enviar cobrança agora')->icon('heroicon-o-envelope')->color('gray')
                ->visible(fn () => auth()->user()->can(Permissao::CobrancaGerenciar->value))
                ->requiresConfirmation()
                ->modalDescription('Envia agora, para as pessoas da empresa, a lista de documentos pendentes e recusados.')
                ->action(function () {
                    $resultado = app(EnviarCobrancaDocumentacao::class)->executar($this->getRecord(), CarbonImmutable::today(), 'manual', auth()->user());

                    match ($resultado) {
                        'sem_pendencias' => Notification::make()->info()->title('A empresa não tem documentos pendentes.')->send(),
                        'sem_destinatario' => Notification::make()->warning()->title('Cobrança não enviada: a empresa não tem usuários no portal (RN-1318).')->send(),
                        default => Notification::make()->success()->title('Cobrança enviada.')->send(),
                    };
                }),
        ];
    }

    protected function beforeSave(): void
    {
        // RN-408 — empresa com contratos em andamento não pode ser encerrada
        $empresa = $this->getRecord();
        $nova = $this->data['situacao'] ?? null;

        if (($nova === SituacaoEmpresa::Encerrada->value || $nova === SituacaoEmpresa::Encerrada)
            && $empresa->contratos()->get()->contains(fn ($c) => $c->situacao->emAndamento())) {
            Notification::make()->danger()->title('A empresa tem funcionários ativos ou em admissão/desligamento e não pode ser encerrada (RN-408).')->send();
            $this->halt();
        }
    }
}
