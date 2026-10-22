<?php

namespace App\Filament\Portal\Resources\Admissoes\Pages;

use App\Actions\Admissao\CancelarAdmissao;
use App\Actions\Admissao\SalvarAdmissao;
use App\Actions\Admissao\SolicitarAdmissao;
use App\Enums\SituacaoContrato;
use App\Exceptions\RegraDeNegocioException;
use App\Filament\Compartilhado\FormularioAdmissao;
use App\Filament\Portal\Resources\Admissoes\AdmissaoResource;
use App\Filament\Portal\Resources\Funcionarios\FuncionarioResource;
use App\Filament\Support\Acao;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/** RN-582 a RN-588 — preencher e solicitar a admissão; o envio fica no final da aba "Resumo e envio" (RN-586). */
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
        return $schema->components(FormularioAdmissao::componentes(
            escritorio: false,
            empresaId: fn () => FormularioAdmissao::empresaDoTenant(),
            acaoEnviar: $this->acaoEnviar(),
        ));
    }

    /** RN-586 — só habilitado sem pendências; salva o que está na tela e solicita (RN-587). */
    private function acaoEnviar(): Action
    {
        return Action::make('enviarParaAnalise')->label('Salvar e enviar para análise do escritório')
            ->icon('heroicon-o-paper-airplane')->color('success')->size('lg')
            ->disabled(fn (Get $get) => FormularioAdmissao::resumo($get, FormularioAdmissao::empresaDoTenant())['pendencias'] !== [])
            ->tooltip(fn (Get $get) => FormularioAdmissao::resumo($get, FormularioAdmissao::empresaDoTenant())['pendencias'] !== []
                ? 'Complete os itens indicados no resumo para enviar.' : null)
            ->requiresConfirmation()
            ->modalHeading('Enviar a admissão ao escritório?')
            ->modalDescription('Os dados serão salvos e enviados. Depois do envio você não poderá mais alterá-los.')
            ->modalSubmitActionLabel('Enviar')
            ->action(function (Action $action) {
                $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                $contrato = $this->getRecord()->refresh();

                if (Acao::rodar(fn () => app(SolicitarAdmissao::class)->executar($contrato, auth()->user()), 'Admissão enviada! O escritório foi avisado.', $action)) {
                    $this->redirect(AdmissaoResource::getUrl('view', ['record' => $contrato]));
                }
            });
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

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label('Salvar rascunho');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Dados salvos.';
    }

    protected function getRedirectUrl(): string
    {
        // volta para a mesma aba em que o usuário estava (fica no endereço da página: ?aba=...)
        parse_str((string) parse_url((string) request()->header('Referer'), PHP_URL_QUERY), $consulta);

        return $this->getResource()::getUrl('edit', array_filter(['record' => $this->getRecord(), 'aba' => $consulta['aba'] ?? null]));
    }

    protected function getHeaderActions(): array
    {
        $contrato = $this->getRecord();

        return [
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
