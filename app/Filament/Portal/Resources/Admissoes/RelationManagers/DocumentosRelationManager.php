<?php

namespace App\Filament\Portal\Resources\Admissoes\RelationManagers;

use App\Enums\DocumentoAdmissao;
use App\Models\Documento;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Portal — documentos do funcionário e a situação de cada um (somente leitura). */
class DocumentosRelationManager extends RelationManager
{
    protected static string $relationship = 'documentos';

    protected static ?string $title = 'Documentos';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tipo')->label('Documento')
                    ->formatStateUsing(fn ($state) => DocumentoAdmissao::tryFrom((string) $state)?->getLabel() ?? $state),
                TextColumn::make('nome_original')->label('Arquivo')
                    ->url(fn (Documento $record) => $record->urlTemporaria(), shouldOpenInNewTab: true)->color('primary'),
                TextColumn::make('created_at')->label('Enviado em')->dateTime('d/m/Y H:i'),
                TextColumn::make('avaliacao')->label('Situação')->badge()
                    ->state(fn (Documento $record) => match ($record->avaliacao) {
                        'aceito' => 'Aceito',
                        'recusado' => 'Recusado',
                        default => 'Em análise',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'Aceito' => 'success',
                        'Recusado' => 'danger',
                        default => 'info',
                    })
                    ->description(fn (Documento $record) => $record->avaliacao === 'recusado' ? $record->motivo_recusa : null),
            ])
            ->paginated(false);
    }
}
