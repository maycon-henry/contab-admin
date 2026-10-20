<?php

namespace App\Filament\Escritorio\Resources\Empresas\RelationManagers;

use App\Domain\DocumentoFiscal;
use App\Domain\Opcoes;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** regras.md RN-411 e RN-412 — matriz e filiais */
class EstabelecimentosRelationManager extends RelationManager
{
    protected static string $relationship = 'estabelecimentos';

    protected static ?string $title = 'Estabelecimentos';

    protected static ?string $modelLabel = 'estabelecimento';

    public function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextInput::make('cnpj')->label('CNPJ')->required()->mask('99.999.999/9999-99')
                ->formatStateUsing(fn ($state) => $state ? DocumentoFiscal::formatarCnpj($state) : $state)
                ->dehydrateStateUsing(fn ($state) => DocumentoFiscal::somenteDigitos($state))
                ->rule(fn () => function ($attribute, $value, $fail) {
                    if (! DocumentoFiscal::cnpjValido($value)) {
                        $fail('CNPJ inválido.');
                    }
                }),
            TextInput::make('nome')->label('Identificação')->placeholder('Filial Centro'),
            TextInput::make('municipio')->label('Município'),
            Select::make('uf')->label('UF')->options(Opcoes::ufs()),
            TextInput::make('cnae_preponderante')->label('CNAE preponderante'),
            Select::make('aliquota_rat')->label('Alíquota RAT')->options(['1.0' => '1%', '2.0' => '2%', '3.0' => '3%']),
            TextInput::make('fap')->label('FAP')->numeric()->minValue(0.5)->maxValue(2)->step(0.0001)->helperText('Simples Nacional: 1,0'),
            Select::make('grau_risco')->label('Grau de risco (NR-4)')->options([1 => '1', 2 => '2', 3 => '3', 4 => '4'])
                ->helperText('Usado na regra do exame demissional (RN-545).'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('matriz')->label('Matriz')->boolean(),
                TextColumn::make('cnpj')->label('CNPJ')->formatStateUsing(fn ($state) => DocumentoFiscal::formatarCnpj($state)),
                TextColumn::make('nome')->label('Identificação'),
                TextColumn::make('municipio')->label('Município')->formatStateUsing(fn ($state, $record) => $state ? "{$state}/{$record->uf}" : '—'),
                TextColumn::make('cnae_preponderante')->label('CNAE'),
                TextColumn::make('aliquota_rat')->label('RAT')->suffix('%'),
                TextColumn::make('fap')->label('FAP'),
                TextColumn::make('grau_risco')->label('Grau de risco'),
            ])
            ->headerActions([CreateAction::make()->label('Adicionar filial')->mutateDataUsing(fn (array $data) => $data + ['matriz' => false])])
            ->recordActions([EditAction::make()]);
    }
}
