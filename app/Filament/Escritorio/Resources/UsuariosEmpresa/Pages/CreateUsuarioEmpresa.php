<?php

namespace App\Filament\Escritorio\Resources\UsuariosEmpresa\Pages;

use App\Actions\Usuarios\ConvidarUsuario;
use App\Enums\TipoUsuario;
use App\Exceptions\RegraDeNegocioException;
use App\Filament\Escritorio\Resources\UsuariosEmpresa\UsuarioEmpresaResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/** RN-242 — convite de usuário da empresa */
class CreateUsuarioEmpresa extends CreateRecord
{
    protected static string $resource = UsuarioEmpresaResource::class;

    protected static ?string $title = 'Convidar usuário do portal';

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(ConvidarUsuario::class)->executar(auth()->user(), $data['name'], $data['email'], TipoUsuario::UsuarioEmpresa, $data['empresas'] ?? []);
        } catch (RegraDeNegocioException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
            $this->halt();
        }
    }

    /** As empresas são vinculadas pela Action; o Filament não deve salvar a relação de novo. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['empresas'] = $this->form->getRawState()['empresas'] ?? [];

        return $data;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Convite enviado por e-mail.';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
