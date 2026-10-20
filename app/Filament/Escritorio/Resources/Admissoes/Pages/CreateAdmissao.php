<?php

namespace App\Filament\Escritorio\Resources\Admissoes\Pages;

use App\Actions\Admissao\SalvarAdmissao;
use App\Exceptions\RegraDeNegocioException;
use App\Filament\Compartilhado\FormularioAdmissao;
use App\Filament\Escritorio\Resources\Admissoes\AdmissaoResource;
use App\Models\Empresa;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/** RN-581 — o escritório inicia a admissão em nome da empresa (começa em rascunho). */
class CreateAdmissao extends CreateRecord
{
    protected static string $resource = AdmissaoResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('empresa_id')->label('Empresa')->required()->searchable()->live()
                ->default(fn () => request()->integer('empresa') ?: null)
                ->options(Empresa::orderBy('razao_social')->pluck('razao_social', 'id')),
            ...FormularioAdmissao::componentes(escritorio: true, empresaId: fn () => $this->data['empresa_id'] ?? null),
        ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(SalvarAdmissao::class)->criar(Empresa::findOrFail($data['empresa_id']), auth()->user(), $data);
        } catch (RegraDeNegocioException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
            $this->halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
