<?php

namespace App\Filament\Portal\Resources\Admissoes\Pages;

use App\Actions\Admissao\SalvarAdmissao;
use App\Exceptions\RegraDeNegocioException;
use App\Filament\Compartilhado\FormularioAdmissao;
use App\Filament\Portal\Resources\Admissoes\AdmissaoResource;
use App\Filament\Portal\Resources\Funcionarios\FuncionarioResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/** RN-580 — a admissão nasce como rascunho */
class CreateAdmissao extends CreateRecord
{
    protected static string $resource = AdmissaoResource::class;

    protected static ?string $title = 'Admitir novo funcionário';

    public function getBreadcrumbs(): array
    {
        return [FuncionarioResource::getUrl('index') => 'Funcionários', 'Admitir novo'];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(FormularioAdmissao::componentes(escritorio: false, empresaId: fn () => FormularioAdmissao::empresaDoTenant()));
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(SalvarAdmissao::class)->criar(filament()->getTenant(), auth()->user(), $data);
        } catch (RegraDeNegocioException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
            $this->halt();
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Rascunho salvo. Complete os dados e documentos e clique em "Solicitar admissão".';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
