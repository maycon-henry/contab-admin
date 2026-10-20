<?php

namespace App\Filament\Compartilhado;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** RN-703 — resultado de cada arquivo do envio (contexto da empresa e portal) */
class ArquivosLoteRelationManager extends RelationManager
{
    protected static string $relationship = 'arquivos';

    protected static ?string $title = 'Arquivos do envio';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome_original')->label('Arquivo')->searchable(),
                TextColumn::make('resultado')->label('Resultado')->badge()
                    ->formatStateUsing(fn ($state) => ['importada' => 'Importada', 'duplicada' => 'Repetida', 'rejeitada' => 'Recusada', 'pendente' => 'Processando'][$state] ?? $state)
                    ->color(fn ($state) => ['importada' => 'success', 'duplicada' => 'gray', 'rejeitada' => 'danger', 'pendente' => 'warning'][$state] ?? 'gray'),
                TextColumn::make('competencia')->label('Competência')->date('m/Y')->placeholder('—'),
                TextColumn::make('notaFiscal.numero')->label('Nota')->placeholder('—'),
                TextColumn::make('motivo')->label('Motivo')->wrap()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('resultado')->options(['importada' => 'Importada', 'duplicada' => 'Repetida', 'rejeitada' => 'Recusada', 'pendente' => 'Processando']),
            ])
            ->poll('5s');
    }
}
