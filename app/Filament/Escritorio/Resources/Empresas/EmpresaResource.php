<?php

namespace App\Filament\Escritorio\Resources\Empresas;

use App\Domain\DocumentoFiscal;
use App\Domain\Opcoes;
use App\Enums\Permissao;
use App\Enums\RegimeTributario;
use App\Enums\SituacaoEmpresa;
use App\Filament\Empresa\Pages\CadastroEmpresa;
use App\Filament\Empresa\Pages\PaginaDaEmpresa;
use App\Filament\Escritorio\Resources\Empresas\Pages\CreateEmpresa;
use App\Filament\Escritorio\Resources\Empresas\Pages\EditEmpresa;
use App\Filament\Escritorio\Resources\Empresas\Pages\ListEmpresas;
use App\Filament\Escritorio\Resources\Empresas\RelationManagers\EstabelecimentosRelationManager;
use App\Filament\Escritorio\Resources\Empresas\RelationManagers\RotinaRelationManager;
use App\Filament\Escritorio\Resources\Empresas\RelationManagers\SociosRelationManager;
use App\Filament\Escritorio\Resources\Empresas\RelationManagers\UsuariosRelationManager;
use App\Models\Empresa;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** regras.md seção 7 — empresas clientes */
class EmpresaResource extends Resource
{
    protected static ?string $model = Empresa::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Cadastros';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'empresa';

    protected static ?string $pluralModelLabel = 'empresas';

    protected static ?string $recordTitleAttribute = 'razao_social';

    public static function canViewAny(): bool
    {
        return auth()->user()->can(Permissao::EmpresasVisualizar->value);
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can(Permissao::EmpresasGerenciar->value);
    }

    public static function canEdit($record): bool
    {
        return auth()->user()->can(Permissao::EmpresasGerenciar->value);
    }

    public static function canDelete($record): bool
    {
        return false; // RN-405 — empresa nunca é excluída
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(self::camposDoCadastro());
    }

    /** Campos do cadastro, usados aqui e na tela "Cadastro da Empresa" do contexto da empresa. */
    public static function camposDoCadastro(): array
    {
        return [
            Tabs::make('empresa')->columnSpanFull()->persistTabInQueryString()->tabs([
                Tab::make('Dados cadastrais')->icon('heroicon-o-identification')->columns(3)->schema([
                    TextInput::make('cnpj')->label('CNPJ')->required()->mask('99.999.999/9999-99')
                        ->disabledOn('edit')
                        ->formatStateUsing(fn ($state) => $state ? DocumentoFiscal::formatarCnpj($state) : $state)
                        ->dehydrateStateUsing(fn ($state) => DocumentoFiscal::somenteDigitos($state))
                        ->rule(fn () => function ($attribute, $value, $fail) {
                            if (! DocumentoFiscal::cnpjValido($value)) {
                                $fail('CNPJ inválido.');
                            }
                        }),
                    TextInput::make('razao_social')->label('Razão social')->required()->columnSpan(2),
                    TextInput::make('nome_fantasia')->label('Nome fantasia'),
                    TextInput::make('natureza_juridica')->label('Natureza jurídica')->required()->placeholder('206-2 — Sociedade Empresária Limitada'),
                    Select::make('regime_tributario')->label('Regime tributário')->options(RegimeTributario::class)->required()
                        ->helperText('Alterações ficam no histórico com vigência (RN-403).'),
                    DatePicker::make('data_inicio_atendimento')->label('Início do atendimento')->required()->default(today()),
                    Select::make('situacao')->label('Situação')->options(SituacaoEmpresa::class)->required()->default(SituacaoEmpresa::Ativa->value),
                    DatePicker::make('data_abertura')->label('Data de abertura'),
                    Select::make('porte')->label('Porte')->options(Opcoes::PORTE),
                    TextInput::make('cnae_principal')->label('CNAE principal'),
                    TextInput::make('capital_social')->label('Capital social')->numeric()->prefix('R$'),
                    TextInput::make('inscricao_estadual')->label('Inscrição estadual')->placeholder('ou "ISENTO"'),
                    TextInput::make('inscricao_municipal')->label('Inscrição municipal'),
                ]),
                Tab::make('Endereço e contato')->icon('heroicon-o-map-pin')->columns(4)->schema([
                    TextInput::make('cep')->label('CEP')->mask('99999-999')->dehydrateStateUsing(fn ($state) => DocumentoFiscal::somenteDigitos($state) ?: null),
                    TextInput::make('logradouro')->label('Logradouro')->columnSpan(2),
                    TextInput::make('numero')->label('Número'),
                    TextInput::make('complemento')->label('Complemento'),
                    TextInput::make('bairro')->label('Bairro'),
                    TextInput::make('municipio')->label('Município'),
                    Select::make('uf')->label('UF')->options(Opcoes::ufs()),
                    TextInput::make('telefone')->label('Telefone')->tel(),
                    TextInput::make('email')->label('E-mail')->email()->columnSpan(3),
                    Section::make('Contato responsável na empresa (RN-407)')->compact()->columns(4)->columnSpanFull()->schema([
                        TextInput::make('contato_nome')->label('Nome'),
                        TextInput::make('contato_cpf')->label('CPF')->mask('999.999.999-99')->dehydrateStateUsing(fn ($state) => DocumentoFiscal::somenteDigitos($state) ?: null),
                        TextInput::make('contato_telefone')->label('Telefone')->tel(),
                        TextInput::make('contato_email')->label('E-mail')->email(),
                    ]),
                ]),
                Tab::make('Dados de empregador')->icon('heroicon-o-users')->columns(2)->schema([
                    Select::make('classificacao_tributaria')->label('Classificação tributária (tabela 8 do eSocial)')->options(Opcoes::CLASSIFICACAO_TRIBUTARIA)
                        ->helperText('Necessário para admitir funcionários (RN-414).'),
                    Toggle::make('indicativo_cooperativa')->label('Cooperativa'),
                    Toggle::make('indicativo_construtora')->label('Construtora'),
                    Toggle::make('indicativo_desoneracao')->label('Desoneração da folha'),
                ]),
                Tab::make('Cobrança')->icon('heroicon-o-bell-alert')->columns(3)->schema([
                    TextInput::make('dia_corte_cobranca')->label('Data de corte própria (dia)')->numeric()->minValue(1)->maxValue(28)
                        ->helperText('Vazio = usa a data de corte padrão do escritório (RN-1311).'),
                    DatePicker::make('cobranca_suspensa_ate')->label('Suspender cobrança até'),
                    TextInput::make('cobranca_suspensa_motivo')->label('Motivo da suspensão')->requiredWith('cobranca_suspensa_ate'),
                ]),
            ]),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('razao_social')->label('Razão social')->searchable()->sortable()
                    ->description(fn (Empresa $record) => $record->nome_fantasia),
                TextColumn::make('cnpj')->label('CNPJ')->formatStateUsing(fn ($state) => DocumentoFiscal::formatarCnpj($state))->searchable(),
                TextColumn::make('regime_tributario')->label('Regime')->badge(),
                TextColumn::make('situacao')->label('Situação')->badge(),
                TextColumn::make('funcionarios_count')->label('Funcionários')->counts('funcionarios')->alignCenter(),
                TextColumn::make('data_inicio_atendimento')->label('Cliente desde')->date('d/m/Y')->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('situacao')->label('Situação')->options(SituacaoEmpresa::class),
                SelectFilter::make('regime_tributario')->label('Regime')->options(RegimeTributario::class),
            ])
            // selecionar a empresa abre o contexto dela, com menu próprio (AGENTS.md 3.3)
            ->recordUrl(fn (Empresa $record) => self::urlContexto($record))
            ->recordActions([
                Action::make('abrir')->label('Página da Empresa')->icon('heroicon-o-arrow-right-circle')->button()
                    ->url(fn (Empresa $record) => self::urlContexto($record)),
            ])
            ->defaultSort('razao_social');
    }

    /** Contexto da empresa: abre a Página da Empresa (ações pendentes), com o menu lateral da empresa. */
    public static function urlContexto(Empresa $empresa, ?string $pagina = null): string
    {
        return $pagina === 'cadastro'
            ? CadastroEmpresa::getUrl(panel: 'empresa', tenant: $empresa)
            : PaginaDaEmpresa::getUrl(panel: 'empresa', tenant: $empresa);
    }

    public static function getRelations(): array
    {
        return [
            SociosRelationManager::class,
            EstabelecimentosRelationManager::class,
            RotinaRelationManager::class,
            UsuariosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmpresas::route('/'),
            'create' => CreateEmpresa::route('/create'),
            'edit' => EditEmpresa::route('/{record}/edit'),
        ];
    }
}
