<?php

namespace App\Filament\Portal\Resources\Admissoes\Pages;

use App\Actions\Admissao\CancelarAdmissao;
use App\Actions\Admissao\SalvarAdmissao;
use App\Actions\Admissao\SolicitarAdmissao;
use App\Domain\Admissao\VerificadorAdmissao;
use App\Enums\SituacaoContrato;
use App\Exceptions\RegraDeNegocioException;
use App\Filament\Compartilhado\FormularioAdmissao;
use App\Filament\Portal\Resources\Admissoes\AdmissaoResource;
use App\Filament\Portal\Resources\Funcionarios\FuncionarioResource;
use App\Filament\Support\Acao;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/** RN-582 a RN-588 — preencher e solicitar a admissão */
class EditAdmissao extends EditRecord
{
    protected static string $resource = AdmissaoResource::class;

    public function getTitle(): string
    {
        return 'Admissão — '.$this->getRecord()->funcionario->nome;
    }

    public function getBreadcrumbs(): array
    {
        return [FuncionarioResource::getUrl('index') => 'Funcionários', 'Admissão'];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(FormularioAdmissao::componentes(escritorio: false, empresaId: fn () => FormularioAdmissao::empresaDoTenant()));
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return FormularioAdmissao::preencher($this->getRecord());
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(SalvarAdmissao::class)->atualizar($record, auth()->user(), $data);
        } catch (RegraDeNegocioException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
            $this->halt();
        }
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Dados salvos.';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }

    protected function getHeaderActions(): array
    {
        $contrato = $this->getRecord();
        $pendencias = app(VerificadorAdmissao::class)->pendenciasDaEmpresa($contrato);

        return [
            // RN-586 — só habilitado sem pendências
            Action::make('solicitar')->label('Solicitar admissão')->icon('heroicon-o-paper-airplane')->color('success')
                ->disabled($pendencias !== [])
                ->tooltip($pendencias !== [] ? 'Complete os dados e documentos obrigatórios (veja a lista de pendências).' : null)
                ->requiresConfirmation()
                ->modalHeading('Solicitar admissão ao escritório?')
                ->modalDescription('Depois de solicitar, você não poderá mais alterar os dados. Salve antes as últimas alterações.')
                ->action(function (Action $action) use ($contrato) {
                    $alertas = [];
                    $ok = Acao::rodar(function () use ($contrato, &$alertas) {
                        $alertas = app(SolicitarAdmissao::class)->executar($contrato, auth()->user());

                        return $alertas;
                    }, 'Admissão solicitada! O escritório foi avisado.', $action);

                    if ($ok) {
                        $this->redirect(AdmissaoResource::getUrl('view', ['record' => $contrato]));
                    }
                }),
            // RN-584 — a empresa pode cancelar o rascunho
            Action::make('cancelar')->label('Desistir da admissão')->icon('heroicon-o-trash')->color('danger')
                ->visible($contrato->situacao === SituacaoContrato::Rascunho)
                ->requiresConfirmation()
                ->action(function (Action $action) use ($contrato) {
                    if (Acao::rodar(fn () => app(CancelarAdmissao::class)->executar($contrato, auth()->user()), 'Admissão cancelada.', $action)) {
                        $this->redirect(FuncionarioResource::getUrl('index'));
                    }
                }),
        ];
    }
}
