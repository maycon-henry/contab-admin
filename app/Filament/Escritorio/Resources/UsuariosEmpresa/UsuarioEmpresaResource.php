<?php

namespace App\Filament\Escritorio\Resources\UsuariosEmpresa;

use App\Enums\Permissao;
use App\Enums\TipoUsuario;
use App\Filament\Escritorio\Resources\Equipe\UsuarioResource;
use App\Filament\Escritorio\Resources\UsuariosEmpresa\Pages\CreateUsuarioEmpresa;
use App\Filament\Escritorio\Resources\UsuariosEmpresa\Pages\EditUsuarioEmpresa;
use App\Filament\Escritorio\Resources\UsuariosEmpresa\Pages\ListUsuariosEmpresa;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** regras.md seção 5.5 — usuários das empresas clientes (portal) */
class UsuarioEmpresaResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Cadastros';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Usuários do portal';

    protected static ?string $modelLabel = 'usuário do portal';

    protected static ?string $pluralModelLabel = 'usuários do portal';

    protected static ?string $slug = 'usuarios-portal';

    public static function canViewAny(): bool
    {
        return auth()->user()->can(Permissao::UsuariosEmpresaGerenciar->value);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('escritorio_id', auth()->user()->escritorio_id)->where('tipo', TipoUsuario::UsuarioEmpresa);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Nome')->required(),
            TextInput::make('email')->label('E-mail')->email()->required()->disabledOn('edit')->unique(ignoreRecord: true),
            Select::make('empresas')->label('Empresas')->multiple()->required()->preload()->columnSpanFull()
                ->relationship('empresas', 'razao_social')
                ->default(fn () => request('empresa') ? [(int) request('empresa')] : [])
                ->helperText('A pessoa só verá as empresas selecionadas (RN-240).'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nome')->searchable(),
                TextColumn::make('email')->label('E-mail')->searchable(),
                TextColumn::make('empresas.razao_social')->label('Empresas')->badge(),
                IconColumn::make('ativo')->label('Ativo')->boolean(),
                TextColumn::make('convite_aceito_em')->label('Convite')->dateTime('d/m/Y')->placeholder('Pendente'),
            ])
            ->filters([SelectFilter::make('empresas')->label('Empresa')->relationship('empresas', 'razao_social')->searchable()->preload()])
            ->recordActions(UsuarioResource::acoesSituacao())
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsuariosEmpresa::route('/'),
            'create' => CreateUsuarioEmpresa::route('/create'),
            'edit' => EditUsuarioEmpresa::route('/{record}/edit'),
        ];
    }
}
