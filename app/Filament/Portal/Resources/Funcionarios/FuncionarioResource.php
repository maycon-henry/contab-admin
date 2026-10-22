<?php

namespace App\Filament\Portal\Resources\Funcionarios;

use App\Domain\DocumentoFiscal;
use App\Enums\SituacaoContrato;
use App\Filament\Portal\Resources\Admissoes\AdmissaoResource;
use App\Filament\Portal\Resources\Funcionarios\Pages\DocumentosFuncionario;
use App\Filament\Portal\Resources\Funcionarios\Pages\ListFuncionarios;
use App\Models\Funcionario;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Portal — funcionários da empresa (RN-243, RN-572). Daqui a empresa admite novos funcionários,
 * vê o cadastro e atualiza: durante a admissão, o formulário completo; depois de admitido,
 * apenas os documentos (RN-565).
 */
class FuncionarioResource extends Resource
{
    protected static ?string $model = Funcionario::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'funcionário';

    protected static ?string $pluralModelLabel = 'funcionários';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('contratoAtual'))
            ->columns([
                TextColumn::make('nome')->label('Nome')->searchable()->weight('medium'),
                TextColumn::make('cpf')->label('CPF')->formatStateUsing(fn ($state) => DocumentoFiscal::formatarCpf($state)),
                TextColumn::make('contratoAtual.cargo')->label('Cargo')->placeholder('—'),
                TextColumn::make('contratoAtual.situacao')->label('Situação')->badge()
                    ->description(fn (Funcionario $record) => match ($record->contratoAtual?->situacao) {
                        SituacaoContrato::Rascunho => 'Complete e solicite a admissão',
                        SituacaoContrato::Devolvida => 'Corrija e solicite novamente',
                        default => null,
                    }),
                TextColumn::make('contratoAtual.data_admissao')->label('Admissão')->date('d/m/Y')->placeholder('—'),
                TextColumn::make('contratoAtual.salario')->label('Salário')->money('BRL')->placeholder('—'),
            ])
            ->recordActions([
                Action::make('visualizar')->label('Visualizar cadastro')->icon('heroicon-o-eye')->color('gray')
                    ->visible(fn (Funcionario $record) => $record->contratoAtual !== null)
                    ->url(fn (Funcionario $record) => AdmissaoResource::getUrl('view', ['record' => $record->contratoAtual])),
                Action::make('atualizar')->label('Atualizar cadastro')->icon('heroicon-o-pencil-square')
                    ->visible(fn (Funcionario $record) => $record->contratoAtual
                        && (AdmissaoResource::canEdit($record->contratoAtual) || AdmissaoResource::podeAtualizarDocumentos($record->contratoAtual)))
                    ->tooltip(fn (Funcionario $record) => $record->contratoAtual && AdmissaoResource::podeAtualizarDocumentos($record->contratoAtual)
                        ? 'Funcionário admitido: você pode trocar os documentos. Os dados são atualizados pelo escritório.' : null)
                    ->url(fn (Funcionario $record) => AdmissaoResource::canEdit($record->contratoAtual)
                        ? AdmissaoResource::getUrl('edit', ['record' => $record->contratoAtual])
                        : AdmissaoResource::getUrl('documentos', ['record' => $record->contratoAtual])),
                Action::make('documentosFuncionario')->label('Documentos')->icon('heroicon-o-folder-open')->color('gray')
                    ->url(fn (Funcionario $record) => DocumentosFuncionario::getUrl(['record' => $record])),
            ])
            ->recordUrl(fn (Funcionario $record) => $record->contratoAtual ? AdmissaoResource::getUrl('view', ['record' => $record->contratoAtual]) : null)
            ->defaultSort('nome');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFuncionarios::route('/'),
            'documentos' => DocumentosFuncionario::route('/{record}/documentos'),
        ];
    }
}
