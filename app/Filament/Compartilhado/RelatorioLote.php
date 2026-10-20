<?php

namespace App\Filament\Compartilhado;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;

/** Colunas e resumo do relatório de cada envio de XMLs — regras.md RN-703. */
final class RelatorioLote
{
    public static function colunas(): array
    {
        return [
            TextColumn::make('created_at')->label('Enviado em')->dateTime('d/m/Y H:i')->sortable(),
            TextColumn::make('enviadoPor.name')->label('Enviado por'),
            TextColumn::make('total_arquivos')->label('Arquivos')->alignCenter(),
            TextColumn::make('importadas')->label('Importadas')->color('success')->alignCenter(),
            TextColumn::make('duplicadas')->label('Repetidas')->color('gray')->alignCenter(),
            TextColumn::make('rejeitadas')->label('Recusadas')->color(fn ($state) => $state ? 'danger' : 'gray')->alignCenter(),
            TextColumn::make('situacao')->label('Situação')->badge()->formatStateUsing(fn ($state) => $state === 'concluido' ? 'Concluído' : 'Processando')
                ->color(fn ($state) => $state === 'concluido' ? 'success' : 'warning'),
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(4)->components([
            TextEntry::make('created_at')->label('Enviado em')->dateTime('d/m/Y H:i'),
            TextEntry::make('enviadoPor.name')->label('Enviado por'),
            TextEntry::make('total_arquivos')->label('Arquivos'),
            TextEntry::make('situacao')->label('Situação')->formatStateUsing(fn ($state) => $state === 'concluido' ? 'Concluído' : 'Processando…'),
            TextEntry::make('importadas')->label('Importadas')->color('success'),
            TextEntry::make('duplicadas')->label('Repetidas'),
            TextEntry::make('rejeitadas')->label('Recusadas')->color('danger'),
        ]);
    }
}
