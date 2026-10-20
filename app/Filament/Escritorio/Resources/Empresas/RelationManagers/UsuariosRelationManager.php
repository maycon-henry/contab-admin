<?php

namespace App\Filament\Escritorio\Resources\Empresas\RelationManagers;

use App\Filament\Escritorio\Resources\UsuariosEmpresa\UsuarioEmpresaResource;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** regras.md seção 5.5 — pessoas da empresa com acesso ao portal */
class UsuariosRelationManager extends RelationManager
{
    protected static string $relationship = 'usuarios';

    protected static ?string $title = 'Usuários do portal';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nome'),
                TextColumn::make('email')->label('E-mail'),
                IconColumn::make('ativo')->label('Ativo')->boolean(),
                TextColumn::make('convite_aceito_em')->label('Convite aceito em')->dateTime('d/m/Y H:i')->placeholder('Convite pendente'),
            ])
            ->headerActions([
                Action::make('convidar')->label('Convidar usuário')->icon('heroicon-o-envelope')
                    ->url(fn () => UsuarioEmpresaResource::getUrl('create', ['empresa' => $this->getOwnerRecord()->id], panel: 'escritorio')),
            ]);
    }
}
