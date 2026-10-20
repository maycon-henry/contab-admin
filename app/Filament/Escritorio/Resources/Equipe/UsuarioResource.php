<?php

namespace App\Filament\Escritorio\Resources\Equipe;

use App\Actions\Usuarios\AlterarSituacaoUsuario;
use App\Actions\Usuarios\ConvidarUsuario;
use App\Actions\Usuarios\DefinirPerfis;
use App\Enums\Perfil;
use App\Enums\Permissao;
use App\Enums\TipoUsuario;
use App\Filament\Escritorio\Resources\Equipe\Pages\CreateUsuario;
use App\Filament\Escritorio\Resources\Equipe\Pages\EditUsuario;
use App\Filament\Escritorio\Resources\Equipe\Pages\ListUsuarios;
use App\Filament\Support\Acao;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** regras.md seções 5.2 a 5.4 — equipe do escritório (somente administradores) */
class UsuarioResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Configurações';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Equipe';

    protected static ?string $modelLabel = 'pessoa da equipe';

    protected static ?string $pluralModelLabel = 'equipe';

    protected static ?string $slug = 'equipe';

    public static function canViewAny(): bool
    {
        return auth()->user()->can(Permissao::EquipeGerenciar->value);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('escritorio_id', auth()->user()->escritorio_id)->daEquipe();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextInput::make('name')->label('Nome')->required(),
            TextInput::make('email')->label('E-mail')->email()->required()->disabledOn('edit')->unique(ignoreRecord: true),
            Select::make('tipo')->label('Tipo')->required()->default(TipoUsuario::Colaborador->value)->live()
                ->options([TipoUsuario::Administrador->value => 'Administrador', TipoUsuario::Colaborador->value => 'Colaborador'])
                ->disabled(fn (?User $record) => $record?->titular)
                ->helperText('O administrador tem acesso total; o colaborador acessa conforme os perfis.'),
            // RN-220 — o colaborador pode acumular perfis
            CheckboxList::make('perfis')->label('Perfis')->columnSpanFull()
                ->options(Perfil::class)
                ->descriptions(collect(Perfil::cases())->mapWithKeys(fn (Perfil $p) => [$p->value => $p->getDescription()])->all())
                ->default([Perfil::Operacional->value])
                ->visible(fn (Get $get) => in_array($get('tipo'), [TipoUsuario::Colaborador, TipoUsuario::Colaborador->value], true))
                ->required()
                ->helperText('Marque um ou mais. As permissões são somadas.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nome')->searchable()->description(fn (User $r) => $r->titular ? 'Administrador titular' : null),
                TextColumn::make('email')->label('E-mail')->searchable(),
                TextColumn::make('tipo')->label('Tipo')->badge(),
                TextColumn::make('perfis')->label('Perfis')->badge()
                    ->state(fn (User $r) => $r->tipo === TipoUsuario::Administrador ? ['Acesso total'] : array_map(fn (Perfil $p) => $p->getLabel(), DefinirPerfis::perfisDe($r)))
                    ->color(fn (string $state) => Perfil::tryFrom($state)?->getColor() ?? 'gray'),
                IconColumn::make('ativo')->label('Ativo')->boolean(),
                TextColumn::make('convite_aceito_em')->label('Convite')->dateTime('d/m/Y')->placeholder('Pendente'),
            ])
            ->recordActions(self::acoesSituacao())
            ->defaultSort('name');
    }

    /** RN-210, RN-211, RN-213, RN-232, RN-233 */
    public static function acoesSituacao(): array
    {
        return [
            EditAction::make(),
            Action::make('reenviarConvite')->label('Reenviar convite')->icon('heroicon-o-envelope')->color('gray')
                ->visible(fn (User $r) => ! $r->conviteAceito())
                ->action(fn (User $r, Action $a) => Acao::rodar(fn () => app(ConvidarUsuario::class)->enviarConvite($r), 'Convite reenviado (o link anterior foi invalidado).', $a)),
            Action::make('desativar')->label('Desativar')->icon('heroicon-o-no-symbol')->color('danger')
                ->visible(fn (User $r) => $r->ativo)->requiresConfirmation()
                ->modalDescription('O acesso é cortado imediatamente, inclusive sessões abertas (RN-233).')
                ->action(fn (User $r, Action $a) => Acao::rodar(fn () => app(AlterarSituacaoUsuario::class)->desativar(auth()->user(), $r), 'Usuário desativado.', $a)),
            Action::make('ativar')->label('Ativar')->icon('heroicon-o-check-circle')->color('success')
                ->visible(fn (User $r) => ! $r->ativo)
                ->action(fn (User $r, Action $a) => Acao::rodar(fn () => app(AlterarSituacaoUsuario::class)->ativar(auth()->user(), $r), 'Usuário ativado.', $a)),
            Action::make('titularidade')->label('Tornar titular')->icon('heroicon-o-star')->color('warning')
                ->visible(fn (User $r) => auth()->user()->titular && $r->tipo === TipoUsuario::Administrador && ! $r->titular && $r->ativo)
                ->requiresConfirmation()->modalDescription('Você deixará de ser o administrador titular (RN-211).')
                ->action(fn (User $r, Action $a) => Acao::rodar(fn () => app(AlterarSituacaoUsuario::class)->transferirTitularidade(auth()->user(), $r), 'Titularidade transferida.', $a)),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsuarios::route('/'),
            'create' => CreateUsuario::route('/create'),
            'edit' => EditUsuario::route('/{record}/edit'),
        ];
    }
}
