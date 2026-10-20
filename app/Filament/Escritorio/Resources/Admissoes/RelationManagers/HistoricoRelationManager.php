<?php

namespace App\Filament\Escritorio\Resources\Admissoes\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** regras.md RN-575 — linha do tempo */
class HistoricoRelationManager extends RelationManager
{
    protected static string $relationship = 'historicos';

    protected static ?string $title = 'Linha do tempo';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Quando')->dateTime('d/m/Y H:i'),
                TextColumn::make('acao')->label('Etapa')->formatStateUsing(fn ($state) => ucfirst(str_replace('_', ' ', $state))),
                TextColumn::make('situacao_nova')->label('Situação')->badge(),
                TextColumn::make('user.name')->label('Por')->placeholder('Sistema'),
                TextColumn::make('descricao')->label('Detalhe')->wrap(),
            ])
            ->defaultSort('id', 'desc')
            ->paginated(false);
    }
}
