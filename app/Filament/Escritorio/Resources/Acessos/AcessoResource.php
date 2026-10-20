<?php

namespace App\Filament\Escritorio\Resources\Acessos;

use App\Enums\Permissao;
use App\Filament\Escritorio\Resources\Acessos\Pages\ListAcessos;
use App\Models\Acesso;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** regras.md RN-330 / RN-331 — histórico de acessos */
class AcessoResource extends Resource
{
    protected static ?string $model = Acesso::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    protected static string|UnitEnum|null $navigationGroup = 'Configurações';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Histórico de acessos';

    protected static ?string $pluralModelLabel = 'histórico de acessos';

    protected static ?string $slug = 'acessos';

    public static function canViewAny(): bool
    {
        return auth()->user()->can(Permissao::AcessosVisualizar->value);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        $eventos = ['login' => 'Login', 'falha_login' => 'Falha de login', 'logout' => 'Saída', 'senha_redefinida' => 'Senha redefinida'];

        return $table
            ->columns([
                TextColumn::make('created_at')->label('Quando')->dateTime('d/m/Y H:i:s')->sortable(),
                TextColumn::make('evento')->label('Evento')->badge()->formatStateUsing(fn ($state) => $eventos[$state] ?? $state)
                    ->color(fn ($state) => $state === 'falha_login' ? 'danger' : 'gray'),
                TextColumn::make('user.name')->label('Usuário')->placeholder('—'),
                TextColumn::make('email')->label('E-mail informado')->searchable(),
                TextColumn::make('ip')->label('IP'),
                TextColumn::make('user_agent')->label('Navegador')->limit(50)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([SelectFilter::make('evento')->options($eventos)])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListAcessos::route('/')];
    }
}
