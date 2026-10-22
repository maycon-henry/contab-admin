<?php

namespace App\Filament\Portal\Resources\Admissoes\Pages;

use App\Actions\Admissao\SalvarAdmissao;
use App\Actions\Admissao\SolicitarAdmissao;
use App\Exceptions\RegraDeNegocioException;
use App\Filament\Compartilhado\Concerns\FormularioDeAdmissao;
use App\Filament\Compartilhado\FormularioAdmissao;
use App\Filament\Portal\Resources\Admissoes\AdmissaoResource;
use App\Filament\Portal\Resources\Funcionarios\FuncionarioResource;
use App\Filament\Support\Acao;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/** RN-580 — a admissão nasce como rascunho; RN-579 e RN-586 — preenchimento automático e envio pelo Resumo. */
class CreateAdmissao extends CreateRecord
{
    use FormularioDeAdmissao;

    protected static string $resource = AdmissaoResource::class;

    protected static ?string $title = 'Admitir novo funcionário';

    protected static bool $canCreateAnother = false;

    /** Botão "Salvar e enviar" do Resumo: cria o rascunho e já solicita a admissão. */
    private bool $enviarAoCriar = false;

    private bool $solicitada = false;

    public function mount(): void
    {
        parent::mount();

        $this->abrirAvisoPreenchimentoAutomatico();
    }

    public function getBreadcrumbs(): array
    {
        return [FuncionarioResource::getUrl('index') => 'Funcionários', 'Admitir novo'];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(FormularioAdmissao::componentes(
            escritorio: false,
            empresaId: fn () => FormularioAdmissao::empresaDoTenant(),
            acaoEnviar: Action::make('salvarEEnviar')->label('Salvar e enviar para análise do escritório')
                ->icon('heroicon-o-paper-airplane')->color('success')->size('lg')
                // RN-586 — só habilitado sem pendências
                ->disabled(fn (Get $get) => FormularioAdmissao::resumo($get, FormularioAdmissao::empresaDoTenant())['pendencias'] !== [])
                ->tooltip(fn (Get $get) => FormularioAdmissao::resumo($get, FormularioAdmissao::empresaDoTenant())['pendencias'] !== []
                    ? 'Complete os itens indicados no resumo para enviar.' : null)
                ->requiresConfirmation()
                ->modalHeading('Enviar a admissão ao escritório?')
                ->modalDescription('Os dados serão salvos e enviados. Depois do envio você não poderá mais alterá-los.')
                ->modalSubmitActionLabel('Enviar')
                ->action(function () {
                    $this->enviarAoCriar = true;
                    $this->create();
                }),
        ));
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Salvar rascunho');
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            $contrato = app(SalvarAdmissao::class)->criar(filament()->getTenant(), auth()->user(), $data);
        } catch (RegraDeNegocioException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
            $this->halt();
        }

        // se ainda houver pendência, o rascunho fica salvo e a lista aparece para o usuário
        if ($this->enviarAoCriar) {
            $this->solicitada = Acao::rodar(fn () => app(SolicitarAdmissao::class)->executar($contrato, auth()->user()),
                'Admissão enviada! O escritório foi avisado.');
        }

        return $contrato;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        if ($this->solicitada) {
            return null; // a notificação do envio já foi mostrada
        }

        return 'Rascunho salvo. Complete os dados e documentos e envie pela aba "Resumo e envio".';
    }

    protected function getRedirectUrl(): string
    {
        return $this->solicitada
            ? $this->getResource()::getUrl('view', ['record' => $this->getRecord()])
            : $this->getResource()::getUrl('edit', array_filter(['record' => $this->getRecord(), 'aba' => $this->enviarAoCriar ? 'resumo' : null]));
    }
}
