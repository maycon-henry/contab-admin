<?php

namespace App\Filament\Escritorio\Resources\Empresas\Pages;

use App\Actions\Empresas\CriarEmpresa;
use App\Exceptions\RegraDeNegocioException;
use App\Filament\Escritorio\Resources\Empresas\EmpresaResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateEmpresa extends CreateRecord
{
    protected static string $resource = EmpresaResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(CriarEmpresa::class)->executar(auth()->user(), $data);
        } catch (RegraDeNegocioException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
            $this->halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return EmpresaResource::urlContexto($this->getRecord(), 'cadastro');
    }
}
