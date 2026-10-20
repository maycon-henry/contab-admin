<?php

namespace App\Filament\Escritorio\Resources\Equipe\Pages;

use App\Actions\Usuarios\ConvidarUsuario;
use App\Enums\TipoUsuario;
use App\Exceptions\RegraDeNegocioException;
use App\Filament\Escritorio\Resources\Equipe\UsuarioResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/** RN-231 — convite por e-mail; o administrador nunca define a senha */
class CreateUsuario extends CreateRecord
{
    protected static string $resource = UsuarioResource::class;

    protected static ?string $title = 'Convidar pessoa da equipe';

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(ConvidarUsuario::class)->executar(auth()->user(), $data['name'], $data['email'], TipoUsuario::from($data['tipo']), perfis: $data['perfis'] ?? []);
        } catch (RegraDeNegocioException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
            $this->halt();
        }
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
