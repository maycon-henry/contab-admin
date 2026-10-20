<?php

namespace App\Filament\Empresa\Resources\Funcionarios;

use App\Enums\Permissao;
use App\Filament\Empresa\Resources\Funcionarios\Pages\EditarCadastroFuncionario;
use App\Filament\Empresa\Resources\Funcionarios\Pages\VerCadastroFuncionario;
use App\Filament\Escritorio\Resources\Admissoes\AdmissaoResource;
use App\Models\Contrato;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;

/**
 * Cadastro do funcionário (contrato atual) dentro do contexto da empresa — AGENTS.md seção 3.3.
 * Reaproveita a ficha e a edição da admissão do painel do escritório, mas mantém o menu
 * lateral da empresa, com "Funcionários" selecionado.
 */
class CadastroFuncionarioResource extends Resource
{
    protected static ?string $model = Contrato::class;

    protected static ?string $tenantRelationshipName = 'contratos';

    protected static ?string $slug = 'funcionarios/cadastro';

    protected static ?string $modelLabel = 'funcionário';

    protected static bool $shouldRegisterNavigation = false;

    public static function canViewAny(): bool
    {
        return auth()->user()->can(Permissao::AdmissoesAnalisar->value);
    }

    public static function canView($record): bool
    {
        return static::canViewAny();
    }

    public static function canEdit($record): bool
    {
        return static::canViewAny() && AdmissaoResource::canEdit($record);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return AdmissaoResource::infolist($schema);
    }

    public static function getRelations(): array
    {
        return AdmissaoResource::getRelations();
    }

    public static function getPages(): array
    {
        return [
            'view' => VerCadastroFuncionario::route('/{record}'),
            'edit' => EditarCadastroFuncionario::route('/{record}/edit'),
        ];
    }
}
