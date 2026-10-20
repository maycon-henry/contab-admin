<?php

namespace App\Filament\Escritorio\Resources\UsuariosEmpresa\Pages;

use App\Filament\Escritorio\Resources\UsuariosEmpresa\UsuarioEmpresaResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditUsuarioEmpresa extends EditRecord
{
    protected static string $resource = UsuarioEmpresaResource::class;

    protected function afterSave(): void
    {
        // RN-246 — sem nenhuma empresa vinculada, o usuário é desativado
        $user = $this->getRecord();
        if ($user->empresas()->count() === 0) {
            $user->update(['ativo' => false]);
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
    }
}
