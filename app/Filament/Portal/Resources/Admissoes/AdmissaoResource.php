<?php

namespace App\Filament\Portal\Resources\Admissoes;

use App\Domain\DocumentoFiscal;
use App\Domain\Opcoes;
use App\Enums\SituacaoContrato;
use App\Filament\Escritorio\Resources\Admissoes\RelationManagers\HistoricoRelationManager;
use App\Filament\Portal\Resources\Admissoes\Pages\AtualizarDocumentos;
use App\Filament\Portal\Resources\Admissoes\Pages\CreateAdmissao;
use App\Filament\Portal\Resources\Admissoes\Pages\EditAdmissao;
use App\Filament\Portal\Resources\Admissoes\Pages\ListAdmissoes;
use App\Filament\Portal\Resources\Admissoes\Pages\ViewAdmissao;
use App\Filament\Portal\Resources\Admissoes\RelationManagers\DocumentosRelationManager;
use App\Models\Contrato;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Portal — cadastro do funcionário (contrato) e admissão pedida pela empresa (regras.md seção 8.3).
 * Fica fora do menu: tudo é acessado pela tela de Funcionários.
 */
class AdmissaoResource extends Resource
{
    protected static ?string $model = Contrato::class;

    protected static ?string $tenantRelationshipName = 'contratos';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'admissão';

    protected static ?string $pluralModelLabel = 'admissões';

    protected static ?string $slug = 'admissoes';

    /** Rascunho ou devolvida: a empresa preenche a admissão (RN-582, RN-588). */
    public static function canEdit($record): bool
    {
        return $record->situacao->editavelPelaEmpresa();
    }

    /** Funcionário admitido: a empresa só troca documentos (RN-565). */
    public static function podeAtualizarDocumentos(Contrato $record): bool
    {
        return $record->situacao === SituacaoContrato::Admitido;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Dados pessoais')->columnSpan(2)->columns(3)->schema([
                TextEntry::make('funcionario.nome')->label('Nome')->columnSpan(2),
                TextEntry::make('funcionario.cpf')->label('CPF')->formatStateUsing(fn ($state) => DocumentoFiscal::formatarCpf($state)),
                TextEntry::make('funcionario.data_nascimento')->label('Nascimento')->date('d/m/Y')->placeholder('—'),
                TextEntry::make('funcionario.sexo')->label('Sexo')->formatStateUsing(fn ($state) => Opcoes::SEXO[$state] ?? $state)->placeholder('—'),
                TextEntry::make('funcionario.estado_civil')->label('Estado civil')->formatStateUsing(fn ($state) => Opcoes::ESTADO_CIVIL[$state] ?? $state)->placeholder('—'),
                TextEntry::make('funcionario.telefone')->label('Telefone')->placeholder('—'),
                TextEntry::make('funcionario.email')->label('E-mail')->placeholder('—'),
                TextEntry::make('endereco')->label('Endereço')->placeholder('—')
                    ->state(fn (Contrato $record) => collect([$record->funcionario->logradouro, $record->funcionario->numero, $record->funcionario->bairro])->filter()->implode(', ')
                        .($record->funcionario->municipio ? ' — '.$record->funcionario->municipio.'/'.$record->funcionario->uf : '')),
            ]),
            Section::make('Situação')->schema([
                TextEntry::make('situacao')->label('Situação')->badge(),
                TextEntry::make('solicitado_em')->label('Admissão solicitada em')->dateTime('d/m/Y H:i')->placeholder('—'),
                TextEntry::make('motivo_devolucao')->label('Motivo da devolução')->placeholder('—')->color('danger')
                    ->visible(fn (Contrato $record) => $record->situacao === SituacaoContrato::Devolvida),
            ]),
            Section::make('Contrato')->columnSpanFull()->columns(4)->schema([
                TextEntry::make('cargo')->label('Cargo')->placeholder('—'),
                TextEntry::make('salario')->label('Salário')->money('BRL')->placeholder('—'),
                TextEntry::make('tipo_contrato')->label('Tipo de contrato')->badge()->placeholder('—'),
                TextEntry::make('jornada_semanal')->label('Jornada semanal')->suffix(' h')->placeholder('—'),
                TextEntry::make('data_admissao_prevista')->label('Início previsto')->date('d/m/Y'),
                TextEntry::make('data_admissao')->label('Admissão efetiva')->date('d/m/Y')->placeholder('—'),
                TextEntry::make('matricula')->label('Matrícula')->placeholder('—'),
                TextEntry::make('horario')->label('Horário')->placeholder('—'),
            ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [DocumentosRelationManager::class, HistoricoRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdmissoes::route('/'),
            'create' => CreateAdmissao::route('/create'),
            'view' => ViewAdmissao::route('/{record}'),
            'edit' => EditAdmissao::route('/{record}/edit'),
            'documentos' => AtualizarDocumentos::route('/{record}/documentos'),
        ];
    }
}
