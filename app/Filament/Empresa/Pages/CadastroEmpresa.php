<?php

namespace App\Filament\Empresa\Pages;

use App\Enums\Permissao;
use App\Enums\SituacaoEmpresa;
use App\Filament\Escritorio\Resources\Empresas\EmpresaResource;
use App\Filament\Escritorio\Resources\Empresas\Pages\EditEmpresa;
use App\Filament\Escritorio\Resources\Empresas\RelationManagers\EstabelecimentosRelationManager;
use App\Filament\Escritorio\Resources\Empresas\RelationManagers\RotinaRelationManager;
use App\Filament\Escritorio\Resources\Empresas\RelationManagers\SociosRelationManager;
use App\Filament\Escritorio\Resources\Empresas\RelationManagers\UsuariosRelationManager;
use App\Filament\Support\Acao;
use App\Models\Empresa;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/** Cadastro da empresa selecionada — regras.md seção 7. */
class CadastroEmpresa extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Cadastro da Empresa';

    protected static ?string $slug = 'cadastro';

    protected string $view = 'filament.empresa.cadastro-empresa';

    public ?array $dados = [];

    public static function canAccess(): bool
    {
        return auth()->user()->can(Permissao::EmpresasVisualizar->value);
    }

    public function empresa(): Empresa
    {
        return Filament::getTenant();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Cadastro da Empresa';
    }

    public function getSubheading(): ?string
    {
        return $this->empresa()->razao_social;
    }

    public function mount(): void
    {
        $this->form->fill($this->empresa()->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        $podeEditar = auth()->user()->can(Permissao::EmpresasGerenciar->value);

        return $schema->statePath('dados')->model($this->empresa())->operation('edit')->disabled(! $podeEditar)->components([
            ...EmpresaResource::camposDoCadastro(),
            Actions::make([Action::make('salvar')->label('Salvar alterações')->action('salvar')])->visible($podeEditar),
        ]);
    }

    public function salvar(): void
    {
        abort_unless(auth()->user()->can(Permissao::EmpresasGerenciar->value), 403);

        $empresa = $this->empresa();
        $dados = $this->form->getState();

        // RN-408 — empresa com contratos em andamento não pode ser encerrada
        $situacao = $dados['situacao'] instanceof SituacaoEmpresa ? $dados['situacao'] : SituacaoEmpresa::tryFrom((string) $dados['situacao']);
        if ($situacao === SituacaoEmpresa::Encerrada && $empresa->contratos()->get()->contains(fn ($c) => $c->situacao->emAndamento())) {
            Notification::make()->danger()->title('A empresa tem funcionários ativos ou em admissão/desligamento e não pode ser encerrada (RN-408).')->send();

            return;
        }

        unset($dados['cnpj']); // CNPJ não muda depois do cadastro
        Acao::rodar(fn () => $empresa->update($dados), 'Cadastro atualizado.');
    }

    /** Abas exibidas abaixo do formulário (sócios, estabelecimentos, rotina, usuários do portal). */
    public function abas(): array
    {
        return [SociosRelationManager::class, EstabelecimentosRelationManager::class, RotinaRelationManager::class, UsuariosRelationManager::class];
    }

    public function paginaDasAbas(): string
    {
        return EditEmpresa::class;
    }
}
