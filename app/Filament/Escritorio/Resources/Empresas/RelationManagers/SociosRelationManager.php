<?php

namespace App\Filament\Escritorio\Resources\Empresas\RelationManagers;

use App\Domain\DocumentoFiscal;
use App\Models\Socio;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** regras.md RN-404 — quadro societário */
class SociosRelationManager extends RelationManager
{
    protected static string $relationship = 'socios';

    protected static ?string $title = 'Sócios';

    protected static ?string $modelLabel = 'sócio';

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('nome')->label('Nome')->required(),
            TextInput::make('documento')->label('CPF ou CNPJ')->required()
                ->dehydrateStateUsing(fn ($state) => DocumentoFiscal::somenteDigitos($state))
                ->rule(fn () => function ($attribute, $value, $fail) {
                    $doc = DocumentoFiscal::somenteDigitos($value);
                    if (! DocumentoFiscal::cpfValido($doc) && ! DocumentoFiscal::cnpjValido($doc)) {
                        $fail('CPF/CNPJ inválido.');
                    }
                }),
            TextInput::make('qualificacao')->label('Qualificação')->placeholder('Sócio-administrador'),
            TextInput::make('percentual')->label('Participação (%)')->numeric()->required()->minValue(0.01)->maxValue(100)
                ->rule(fn (?Socio $record) => function ($attribute, $value, $fail) use ($record) {
                    $outros = $this->getOwnerRecord()->socios()->when($record, fn ($q) => $q->whereKeyNot($record->id))->sum('percentual');
                    if ($outros + (float) $value > 100.0001) {
                        $fail('A soma das participações não pode passar de 100% (RN-404).');
                    }
                }),
            DatePicker::make('data_entrada')->label('Data de entrada'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')->label('Nome'),
                TextColumn::make('documento')->label('CPF/CNPJ')->formatStateUsing(fn ($state) => DocumentoFiscal::formatar($state)),
                TextColumn::make('qualificacao')->label('Qualificação'),
                TextColumn::make('percentual')->label('Participação')->suffix('%')
                    ->summarize(Sum::make()->label('Total')->suffix('%')),
                TextColumn::make('data_entrada')->label('Entrada')->date('d/m/Y'),
            ])
            ->headerActions([CreateAction::make()->label('Adicionar sócio')])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
