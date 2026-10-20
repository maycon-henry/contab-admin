<?php

namespace App\Filament\Portal\Resources\Admissoes\Pages;

use App\Actions\Admissao\AtualizarDocumentosDoFuncionario;
use App\Exceptions\RegraDeNegocioException;
use App\Filament\Compartilhado\FormularioAdmissao;
use App\Filament\Portal\Resources\Admissoes\AdmissaoResource;
use App\Filament\Portal\Resources\Funcionarios\FuncionarioResource;
use App\Models\Contrato;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/**
 * Portal — funcionário admitido: a empresa só troca ou inclui documentos (regras.md RN-565 a RN-567).
 * Os dados do funcionário são atualizados pelo Operacional do escritório.
 */
class AtualizarDocumentos extends EditRecord
{
    protected static string $resource = AdmissaoResource::class;

    private int $alterados = 0;

    public static function canAccess(array $parameters = []): bool
    {
        $record = $parameters['record'] ?? null;
        if ($record !== null && ! $record instanceof Contrato) {
            $record = AdmissaoResource::resolveRecordRouteBinding($record);
        }

        return $record instanceof Contrato && AdmissaoResource::podeAtualizarDocumentos($record);
    }

    /** Não é a edição da admissão (RN-588): só a troca de documentos de funcionário admitido. */
    protected function authorizeAccess(): void
    {
        abort_unless(AdmissaoResource::podeAtualizarDocumentos($this->getRecord()), 403);
    }

    public function getTitle(): string
    {
        return 'Atualizar documentos — '.$this->getRecord()->funcionario->nome;
    }

    public function getBreadcrumbs(): array
    {
        return [FuncionarioResource::getUrl('index') => 'Funcionários', 'Atualizar documentos'];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Callout::make('Como funciona')
                ->info()
                ->description('Troque ou inclua os documentos do funcionário. Cada documento enviado fica "em análise" até o escritório conferir e aceitar. Para corrigir dados do funcionário (endereço, cargo, salário...), fale com o escritório.')
                ->columnSpanFull(),
            Section::make('Documentos')->columns(2)->columnSpanFull()->schema(FormularioAdmissao::camposDocumentos()),
        ]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return FormularioAdmissao::preencher($this->getRecord());
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            $alterados = app(AtualizarDocumentosDoFuncionario::class)->executar($record, auth()->user(), $data['documentos'] ?? [], $data['documentos_nomes'] ?? []);
        } catch (RegraDeNegocioException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
            $this->halt();
        }

        $this->alterados = count($alterados);

        return $record;
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return $this->alterados ? 'Documentos enviados. O escritório vai conferir.' : 'Nenhum documento foi alterado.';
    }

    protected function getRedirectUrl(): string
    {
        return AdmissaoResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
