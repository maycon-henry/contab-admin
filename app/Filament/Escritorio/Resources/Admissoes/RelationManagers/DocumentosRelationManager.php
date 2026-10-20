<?php

namespace App\Filament\Escritorio\Resources\Admissoes\RelationManagers;

use App\Actions\Admissao\AvaliarDocumentoAdmissao;
use App\Enums\DocumentoAdmissao;
use App\Enums\SituacaoContrato;
use App\Filament\Support\Acao;
use App\Models\Documento;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** regras.md RN-593 — conferência de cada documento da admissão */
class DocumentosRelationManager extends RelationManager
{
    protected static string $relationship = 'documentos';

    protected static ?string $title = 'Documentos';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        // RN-593 (admissão em análise) e RN-567 (documento trocado de funcionário admitido)
        $podeAvaliar = fn (Documento $record) => $this->getOwnerRecord()->situacao === SituacaoContrato::EmAnalise
            || ($this->getOwnerRecord()->situacao === SituacaoContrato::Admitido && $record->avaliacao === null);

        return $table
            ->columns([
                TextColumn::make('tipo')->label('Documento')
                    ->formatStateUsing(fn ($state) => DocumentoAdmissao::tryFrom($state)?->getLabel() ?? str_replace('_', ' ', ucfirst($state)))
                    ->description(fn (Documento $record) => DocumentoAdmissao::tryFrom($record->tipo)?->obrigatorio() ? 'Obrigatório' : null),
                TextColumn::make('nome_original')->label('Arquivo')
                    ->url(fn (Documento $record) => $record->urlTemporaria(), shouldOpenInNewTab: true)->color('primary'),
                TextColumn::make('origem')->label('Enviado por')->formatStateUsing(fn ($state, Documento $record) => ($state === 'empresa' ? 'Empresa' : 'Escritório').' — '.$record->enviadoPor?->name),
                TextColumn::make('created_at')->label('Em')->dateTime('d/m/Y H:i'),
                TextColumn::make('avaliacao')->label('Avaliação')->badge()->placeholder('Em análise')
                    ->formatStateUsing(fn ($state) => $state === 'aceito' ? 'Aceito' : 'Recusado')
                    ->color(fn ($state) => $state === 'aceito' ? 'success' : 'danger')
                    ->description(fn (Documento $record) => $record->motivo_recusa),
            ])
            ->recordActions([
                Action::make('aceitar')->label('Aceitar')->icon('heroicon-o-check')->color('success')->visible($podeAvaliar)
                    ->action(fn (Documento $record, Action $a) => Acao::rodar(fn () => app(AvaliarDocumentoAdmissao::class)->executar($record, auth()->user(), true), 'Documento aceito.', $a)),
                Action::make('recusar')->label('Recusar')->icon('heroicon-o-x-mark')->color('danger')->visible($podeAvaliar)
                    ->schema([Textarea::make('motivo')->label('Motivo')->required()])
                    ->action(fn (Documento $record, array $data, Action $a) => Acao::rodar(fn () => app(AvaliarDocumentoAdmissao::class)->executar($record, auth()->user(), false, $data['motivo']), 'Documento recusado.', $a)),
            ])
            ->paginated(false);
    }
}
