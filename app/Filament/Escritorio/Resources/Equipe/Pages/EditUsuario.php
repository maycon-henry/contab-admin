<?php

namespace App\Filament\Escritorio\Resources\Equipe\Pages;

use App\Actions\Usuarios\DefinirAtribuicoes;
use App\Enums\Atribuicao;
use App\Exceptions\RegraDeNegocioException;
use App\Filament\Escritorio\Resources\Equipe\UsuarioResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class EditUsuario extends EditRecord
{
    protected static string $resource = UsuarioResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['atribuicoes'] = array_map(fn (Atribuicao $a) => $a->value, DefinirAtribuicoes::atribuicoesDe($this->getRecord()));

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            // RN-234 — tipo e atribuições passam a valer imediatamente
            return DB::transaction(function () use ($record, $data) {
                $record->update(Arr::only($data, ['name', 'tipo']));
                app(DefinirAtribuicoes::class)->executar($record, $data['atribuicoes'] ?? []);

                return $record;
            });
        } catch (RegraDeNegocioException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
            $this->halt();
        }
    }
}
