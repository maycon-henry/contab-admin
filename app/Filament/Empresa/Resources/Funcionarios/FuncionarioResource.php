<?php

namespace App\Filament\Empresa\Resources\Funcionarios;

use App\Enums\Permissao;
use App\Enums\SituacaoContrato;
use App\Filament\Empresa\Resources\Funcionarios\Pages\DocumentosFuncionario;
use App\Filament\Empresa\Resources\Funcionarios\Pages\ListFuncionarios;
use App\Models\Funcionario;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Funcionários da empresa selecionada — regras.md seção 8. */
class FuncionarioResource extends Resource
{
    protected static ?string $model = Funcionario::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Funcionários';

    protected static ?string $modelLabel = 'funcionário';

    protected static ?string $pluralModelLabel = 'funcionários';

    public static function canViewAny(): bool
    {
        return auth()->user()->can(Permissao::FuncionariosVisualizar->value);
    }

    public static function canCreate(): bool
    {
        return false; // funcionários entram pela admissão (seção 8.3)
    }

    public static function table(Table $table): Table
    {
        $verSensiveis = fn () => auth()->user()->can(Permissao::FuncionariosDadosSensiveis->value);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('contratoAtual'))
            ->columns([
                TextColumn::make('nome')->label('Nome')->searchable()->sortable(),
                TextColumn::make('contratoAtual.cargo')->label('Cargo'),
                TextColumn::make('contratoAtual.situacao')->label('Situação')->badge(),
                TextColumn::make('contratoAtual.data_admissao')->label('Admissão')->date('d/m/Y')->placeholder('—'),
                TextColumn::make('contratoAtual.salario')->label('Salário')->money('BRL')->visible($verSensiveis),
                TextColumn::make('contratos_count')->label('Contratos')->counts('contratos')->alignCenter()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('situacao')->label('Situação do contrato atual')->options(SituacaoContrato::class)
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn ($q, $v) => $q->whereHas('contratoAtual', fn ($q) => $q->where('situacao', $v)))),
            ])
            ->recordActions([
                Action::make('documentosFuncionario')->label('Documentos')->icon('heroicon-o-folder-open')
                    ->url(fn (Funcionario $record) => DocumentosFuncionario::getUrl(['record' => $record])),
                Action::make('contrato')->label('Cadastro')->icon('heroicon-o-identification')
                    ->visible(fn (Funcionario $record) => $record->contratoAtual !== null && auth()->user()->can(Permissao::AdmissoesAnalisar->value))
                    ->url(fn (Funcionario $record) => CadastroFuncionarioResource::getUrl('view', ['record' => $record->contratoAtual])),
            ])
            ->defaultSort('nome');
    }

    /** "Funcionários" continua selecionado no menu enquanto se vê ou edita o cadastro. */
    public static function getNavigationItemActiveRoutePattern(): string|array
    {
        return [static::getRouteBaseName().'.*', CadastroFuncionarioResource::getRouteBaseName().'.*'];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFuncionarios::route('/'),
            'documentos' => DocumentosFuncionario::route('/{record}/documentos'),
        ];
    }
}
