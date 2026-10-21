<?php

namespace App\Filament\Escritorio\Pages;

use App\Actions\Despesas\SalvarConfigDocumentosDespesa;
use App\Domain\Opcoes;
use App\Enums\Permissao;
use App\Enums\TipoDocumentoDespesa;
use App\Filament\Support\Acao;
use App\Models\ConfigDocumentoDespesa;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
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
        $escritorio = auth()->user()->escritorio;

        $this->form->fill($escritorio->only(['razao_social', 'crc', 'cobranca_automatica_ativa', 'dia_corte_cobranca']) + [
            'documentos_despesa' => ConfigDocumentoDespesa::doEscritorio($escritorio->id)
                ->map(fn (ConfigDocumentoDespesa $c) => ['formatos' => $c->formatos, 'tamanho_maximo_mb' => $c->tamanho_maximo_mb])->all(),
        ]);
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
            // RN-1610 — formatos e tamanho aceitos em cada tipo de documento das despesas
            Section::make('Documentos das despesas')
                ->description('Formatos aceitos e tamanho máximo de cada documento que pode ser anexado a uma despesa.')
                ->schema(collect(TipoDocumentoDespesa::cases())->map(fn (TipoDocumentoDespesa $tipo) => Grid::make(3)->schema([
                    CheckboxList::make("documentos_despesa.{$tipo->value}.formatos")->label($tipo->getLabel())
                        ->options(Opcoes::FORMATOS)->columns(4)->required()->columnSpan(2),
                    TextInput::make("documentos_despesa.{$tipo->value}.tamanho_maximo_mb")->label('Tamanho máximo')->suffix('MB')
                        ->numeric()->minValue(1)->maxValue(SalvarConfigDocumentosDespesa::TAMANHO_MAXIMO_PERMITIDO_MB)->required(),
                ]))->all()),
            Actions::make([Action::make('salvar')->label('Salvar')->action('salvar')]),
        ]);
    }

    public function salvar(): void
    {
        $dados = $this->form->getState();

        $ok = Acao::rodar(function () use ($dados) {
            DB::transaction(function () use ($dados) {
                auth()->user()->escritorio->update(Arr::only($dados, ['razao_social', 'crc', 'cobranca_automatica_ativa', 'dia_corte_cobranca']));
                app(SalvarConfigDocumentosDespesa::class)->executar(auth()->user(), $dados['documentos_despesa'] ?? []);
            });
        });

        if ($ok) {
            Notification::make()->success()->title('Configurações salvas.')->send();
        }
    }
}
