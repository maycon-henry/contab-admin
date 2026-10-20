<?php

namespace App\Filament\Portal\Pages;

use App\Domain\DocumentoFiscal;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** Portal — dados cadastrais da empresa (somente leitura, RN-243) */
class DadosDaEmpresa extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 9;

    protected static ?string $title = 'Dados da empresa';

    protected static ?string $slug = 'dados-da-empresa';

    protected string $view = 'filament.portal.dados-da-empresa';

    public function infolist(Schema $schema): Schema
    {
        return $schema->record(filament()->getTenant())->components([
            Section::make('Cadastro')->columns(3)->schema([
                TextEntry::make('razao_social')->label('Razão social'),
                TextEntry::make('nome_fantasia')->label('Nome fantasia')->placeholder('—'),
                TextEntry::make('cnpj')->label('CNPJ')->formatStateUsing(fn ($state) => DocumentoFiscal::formatarCnpj($state)),
                TextEntry::make('regime_tributario')->label('Regime tributário'),
                TextEntry::make('inscricao_estadual')->label('Inscrição estadual')->placeholder('—'),
                TextEntry::make('inscricao_municipal')->label('Inscrição municipal')->placeholder('—'),
                TextEntry::make('endereco')->label('Endereço')->columnSpan(3)
                    ->state(fn ($record) => trim("{$record->logradouro}, {$record->numero} {$record->complemento} — {$record->bairro} — {$record->municipio}/{$record->uf}")),
            ]),
            Section::make('Sócios')->schema([
                TextEntry::make('socios')->label('')->listWithLineBreaks()
                    ->state(fn ($record) => $record->socios->map(fn ($s) => "{$s->nome} — {$s->qualificacao} ({$s->percentual}%)")->all()),
            ]),
        ]);
    }
}
