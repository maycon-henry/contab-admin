<?php

namespace App\Filament\Escritorio\Pages;

use App\Enums\Permissao;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Dados do escritório e cobrança automática — regras.md RN-101, RN-1311, RN-1317 (exclusivo do administrador, RN-223) */
class ConfiguracoesEscritorio extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Configurações';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Dados do escritório e cobrança';

    protected static ?string $navigationLabel = 'Escritório e cobrança';

    protected static ?string $slug = 'configuracoes';

    protected string $view = 'filament.escritorio.configuracoes';

    public ?array $dados = [];

    public static function canAccess(): bool
    {
        return auth()->user()->can(Permissao::EscritorioConfigurar->value);
    }

    public function mount(): void
    {
        $this->form->fill(auth()->user()->escritorio->only(['razao_social', 'crc', 'cobranca_automatica_ativa', 'dia_corte_cobranca']));
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('dados')->components([
            Section::make('Escritório')->columns(2)->schema([
                TextInput::make('razao_social')->label('Razão social')->required(),
                TextInput::make('crc')->label('CRC do responsável técnico'),
            ]),
            Section::make('Cobrança diária de documentação (seção 16.1)')->columns(2)
                ->description('Todos os dias úteis, às 8h, as empresas com documentos pendentes ou recusados recebem um e-mail a partir da data de corte.')
                ->schema([
                    Toggle::make('cobranca_automatica_ativa')->label('Cobrança automática ativa'),
                    TextInput::make('dia_corte_cobranca')->label('Data de corte padrão (dia do mês)')->numeric()->minValue(1)->maxValue(28)->required()
                        ->helperText('Cada empresa pode ter a sua própria data de corte (cadastro da empresa → Cobrança).'),
                ]),
            Actions::make([Action::make('salvar')->label('Salvar')->action('salvar')]),
        ]);
    }

    public function salvar(): void
    {
        auth()->user()->escritorio->update($this->form->getState());

        Notification::make()->success()->title('Configurações salvas.')->send();
    }
}
