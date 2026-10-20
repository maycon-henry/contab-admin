<?php

namespace App\Filament\Escritorio\Resources\Admissoes;

use App\Actions\Admissao\AssumirAdmissao;
use App\Domain\DocumentoFiscal;
use App\Domain\Opcoes;
use App\Enums\Permissao;
use App\Enums\SituacaoContrato;
use App\Filament\Escritorio\Resources\Admissoes\Pages\CreateAdmissao;
use App\Filament\Escritorio\Resources\Admissoes\Pages\EditAdmissao;
use App\Filament\Escritorio\Resources\Admissoes\Pages\ListAdmissoes;
use App\Filament\Escritorio\Resources\Admissoes\Pages\ViewAdmissao;
use App\Filament\Escritorio\Resources\Admissoes\RelationManagers\DocumentosRelationManager;
use App\Filament\Escritorio\Resources\Admissoes\RelationManagers\HistoricoRelationManager;
use App\Filament\Support\Acao;
use App\Models\Contrato;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Fila de admissões e contratos — regras.md seções 8.3 a 8.5 */
class AdmissaoResource extends Resource
{
    protected static ?string $model = Contrato::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static string|UnitEnum|null $navigationGroup = 'Departamento pessoal';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Fila de admissões';

    protected static ?string $modelLabel = 'admissão';

    protected static ?string $pluralModelLabel = 'admissões';

    protected static ?string $slug = 'admissoes';

    public static function canViewAny(): bool
    {
        return auth()->user()->can(Permissao::AdmissoesAnalisar->value);
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can(Permissao::AdmissoesAnalisar->value);
    }

    public static function canEdit($record): bool
    {
        return in_array($record->situacao, [SituacaoContrato::Rascunho, SituacaoContrato::Devolvida, SituacaoContrato::EmAnalise, SituacaoContrato::Admitido], true);
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $total = Contrato::where('situacao', SituacaoContrato::EmAnalise)->whereNull('responsavel_id')->count();

        return $total ? (string) $total : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Admissões em análise sem responsável';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('funcionario.nome')->label('Funcionário')->searchable()->weight('medium')
                    ->description(fn (Contrato $record) => $record->cargo),
                TextColumn::make('empresa.razao_social')->label('Empresa')->searchable()->toggleable(),
                TextColumn::make('data_admissao_prevista')->label('Início previsto')->date('d/m/Y')->sortable(),
                // RN-591 — destaque para o prazo do eSocial (dia anterior ao início)
                TextColumn::make('prazo_esocial')->label('Prazo eSocial')
                    ->state(fn (Contrato $record) => $record->situacao->emAdmissao() ? $record->data_admissao_prevista?->copy()->subDay() : null)
                    ->date('d/m/Y')->placeholder('—')
                    ->color(fn (Contrato $record) => $record->situacao->emAdmissao() && $record->data_admissao_prevista?->copy()->subDay()->lte(today()->addWeekday()) ? 'danger' : null),
                TextColumn::make('solicitado_em')->label('Solicitada em')->dateTime('d/m/Y H:i')->placeholder('—')->sortable(),
                TextColumn::make('responsavel.name')->label('Responsável')->placeholder('Sem responsável'),
                TextColumn::make('situacao')->label('Situação')->badge(),
            ])
            ->filters([
                SelectFilter::make('empresa_id')->label('Empresa')->relationship('empresa', 'razao_social')->searchable()->preload(),
                SelectFilter::make('responsavel_id')->label('Responsável')
                    ->options(fn () => User::where('escritorio_id', auth()->user()->escritorio_id)->daEquipe()->orderBy('name')->pluck('name', 'id')),
            ])
            ->recordActions([
                Action::make('assumir')->label('Assumir')->icon('heroicon-o-hand-raised')->button()
                    ->visible(fn (Contrato $record) => $record->situacao === SituacaoContrato::EmAnalise && $record->responsavel_id === null)
                    ->requiresConfirmation()->modalDescription('Você passa a ser o responsável e fica registrado que iniciou o atendimento (RN-592).')
                    ->action(fn (Contrato $record, Action $action) => Acao::rodar(fn () => app(AssumirAdmissao::class)->executar($record, auth()->user()), 'Admissão assumida.', $action)),
                ViewAction::make()->label('Abrir'),
            ])
            ->defaultSort('data_admissao_prevista');
    }

    public static function infolist(Schema $schema): Schema
    {
        $podeVerSensiveis = fn () => auth()->user()->can(Permissao::FuncionariosDadosSensiveis->value);

        return $schema->columns(3)->components([
            Section::make('Funcionário')->columnSpan(2)->columns(3)->schema([
                TextEntry::make('funcionario.nome')->label('Nome')->columnSpan(2),
                TextEntry::make('funcionario.cpf')->label('CPF')->formatStateUsing(fn ($state) => DocumentoFiscal::formatarCpf($state)),
                TextEntry::make('funcionario.data_nascimento')->label('Nascimento')->date('d/m/Y'),
                TextEntry::make('funcionario.sexo')->label('Sexo')->formatStateUsing(fn ($state) => Opcoes::SEXO[$state] ?? $state),
                TextEntry::make('funcionario.grau_instrucao')->label('Instrução')->formatStateUsing(fn ($state) => Opcoes::GRAU_INSTRUCAO[$state] ?? $state),
                TextEntry::make('funcionario.municipio')->label('Endereço')->formatStateUsing(fn ($state, Contrato $record) => trim("{$record->funcionario->logradouro}, {$record->funcionario->numero} — {$state}/{$record->funcionario->uf}", ' ,—/'))->columnSpan(2),
                TextEntry::make('funcionario.telefone')->label('Telefone')->placeholder('—'),
                TextEntry::make('funcionario.dependentes_count')->label('Dependentes')->state(fn (Contrato $record) => $record->funcionario->dependentes()->count()),
            ]),
            Section::make('Atendimento (RN-550)')->columnSpan(1)->schema([
                TextEntry::make('situacao')->label('Situação')->badge(),
                TextEntry::make('responsavel.name')->label('Responsável')->placeholder('Sem responsável'),
                TextEntry::make('solicitadoPor.name')->label('Solicitada por')->placeholder('—')
                    ->helperText(fn (Contrato $record) => $record->solicitado_em?->format('d/m/Y H:i')),
                TextEntry::make('atendimentoIniciadoPor.name')->label('Atendimento iniciado por')->placeholder('—')
                    ->helperText(fn (Contrato $record) => $record->atendimento_iniciado_em?->format('d/m/Y H:i')),
                TextEntry::make('admitidoPor.name')->label('Admitido por')->placeholder('—')
                    ->helperText(fn (Contrato $record) => $record->admitido_em?->format('d/m/Y H:i')),
            ]),
            Section::make('Contrato')->columnSpanFull()->columns(4)->schema([
                TextEntry::make('empresa.razao_social')->label('Empresa'),
                TextEntry::make('estabelecimento.rotulo')->label('Local de trabalho')->placeholder('—'),
                TextEntry::make('cargo')->label('Cargo')->placeholder('—'),
                TextEntry::make('salario')->label('Salário')->money('BRL')->visible($podeVerSensiveis)->placeholder('—'),
                TextEntry::make('tipo_contrato')->label('Tipo de contrato')->placeholder('—'),
                TextEntry::make('experiencia')->label('Experiência')->state(fn (Contrato $record) => $record->experiencia_dias_1 ? "{$record->experiencia_dias_1} + ".((int) $record->experiencia_dias_2).' dias' : null)->placeholder('—'),
                TextEntry::make('jornada_semanal')->label('Jornada semanal')->suffix(' h')->placeholder('—'),
                TextEntry::make('data_admissao_prevista')->label('Início previsto')->date('d/m/Y'),
                TextEntry::make('matricula')->label('Matrícula')->placeholder('—'),
                TextEntry::make('data_admissao')->label('Admissão efetiva')->date('d/m/Y')->placeholder('—'),
                TextEntry::make('cbo')->label('CBO')->placeholder('—'),
                TextEntry::make('categoria')->label('Categoria')->formatStateUsing(fn ($state) => Opcoes::CATEGORIA[$state] ?? $state)->placeholder('—'),
            ]),
            Section::make('Desligamento')->columnSpanFull()->columns(4)
                ->visible(fn (Contrato $record) => $record->data_desligamento !== null)
                ->schema([
                    TextEntry::make('motivo_desligamento')->label('Motivo')->columnSpan(2),
                    TextEntry::make('data_desligamento')->label('Data de desligamento')->date('d/m/Y'),
                    TextEntry::make('tipo_aviso')->label('Aviso prévio'),
                    TextEntry::make('dias_aviso')->label('Dias de aviso')->suffix(' dias'),
                    TextEntry::make('data_projetada_fim_aviso')->label('Fim projetado do aviso')->date('d/m/Y')->placeholder('—'),
                    TextEntry::make('prazo_pagamento')->label('Prazo pagamento rescisão (RN-544)')
                        ->state(fn (Contrato $record) => $record->data_desligamento?->copy()->addDays(10))->date('d/m/Y'),
                    TextEntry::make('prazo_s2299')->label('Prazo S-2299 (eSocial)')
                        ->state(fn (Contrato $record) => $record->data_desligamento?->copy()->addDays(10))->date('d/m/Y'),
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
        ];
    }
}
