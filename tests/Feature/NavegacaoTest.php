<?php

use App\Actions\Usuarios\DefinirPerfis;
use App\Enums\Perfil;
use App\Enums\SituacaoContrato;
use App\Exceptions\RegraDeNegocioException;
use App\Filament\Compartilhado\ArquivosLoteRelationManager;
use App\Filament\Empresa\Resources\LotesImportacao\Pages\ViewLote;
use App\Filament\Escritorio\Resources\Admissoes\Pages\ViewAdmissao;
use App\Filament\Escritorio\Resources\Admissoes\RelationManagers\DocumentosRelationManager;
use App\Filament\Escritorio\Resources\Admissoes\RelationManagers\HistoricoRelationManager;
use App\Filament\Escritorio\Resources\Empresas\Pages\EditEmpresa;
use App\Filament\Escritorio\Resources\Empresas\RelationManagers\EstabelecimentosRelationManager;
use App\Filament\Escritorio\Resources\Empresas\RelationManagers\RotinaRelationManager;
use App\Filament\Escritorio\Resources\Empresas\RelationManagers\SociosRelationManager;
use App\Filament\Escritorio\Resources\Empresas\RelationManagers\UsuariosRelationManager;
use App\Filament\Escritorio\Resources\Equipe\Pages\CreateUsuario;
use App\Filament\Escritorio\Resources\Equipe\Pages\EditUsuario;
use App\Filament\Escritorio\Resources\FilaAvaliacao\Pages\ListFilaAvaliacao;
use App\Filament\Escritorio\Widgets\EmpresasDevendoDocumentacao;
use App\Filament\Escritorio\Widgets\IndicadoresEmpresas;
use App\Filament\Portal\Widgets\ResumoDoMes;
use App\Models\Contrato;
use App\Models\Empresa;
use App\Models\LoteImportacaoXml;
use App\Models\TipoDocumento;
use App\Models\User;
use Database\Seeders\DesenvolvimentoSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DesenvolvimentoSeeder::class);
    $this->admin = User::where('email', 'admin@contab.test')->first();
    $this->colaborador = User::where('email', 'colaborador@contab.test')->first();
    $this->usuarioEmpresa = User::where('email', 'empresa@contab.test')->first();
});

function entrarComo(User $user): void
{
    test()->actingAs($user);
    setPermissionsTeamId($user->escritorio_id);
}

it('abre as páginas públicas', function () {
    $this->get('/')->assertOk()->assertSee('Entrar');
    $this->get('/admin/login')->assertOk();
    $this->get('/login')->assertRedirect('/admin/login');
});

it('abre todas as páginas do painel do escritório como administrador', function () {
    entrarComo($this->admin);

    $empresa = Empresa::where('cnpj', 'like', '22333444%')->first();
    $emAnalise = Contrato::where('situacao', SituacaoContrato::EmAnalise)->first();
    $admitido = Contrato::where('situacao', SituacaoContrato::Admitido)->first();

    $paginas = [
        '/admin', '/admin/empresas', '/admin/empresas/create', "/admin/empresas/{$empresa->id}/edit",
        '/admin/fila-de-avaliacao',
        '/admin/cobrancas', '/admin/admissoes', '/admin/admissoes/create', "/admin/admissoes/{$emAnalise->id}",
        "/admin/admissoes/{$emAnalise->id}/edit", "/admin/admissoes/{$admitido->id}",
        '/admin/catalogo-documentos', '/admin/catalogo-documentos/create', '/admin/catalogo-documentos/'.TipoDocumento::first()->id.'/edit',
        '/admin/equipe', '/admin/equipe/create', "/admin/equipe/{$this->colaborador->id}/edit",
        '/admin/usuarios-portal', '/admin/usuarios-portal/create', "/admin/usuarios-portal/{$this->usuarioEmpresa->id}/edit",
        '/admin/acessos', '/admin/relatorios/admissoes', '/admin/configuracoes',
    ];

    foreach ($paginas as $pagina) {
        expect($this->get($pagina)->status())->toBe(200, "Falhou: {$pagina}");
    }

    // a área de trabalho foi removida do painel do escritório
    expect($this->get('/admin/competencias')->status())->toBe(404);
});

it('renderiza abas e widgets do painel do escritório', function () {
    entrarComo($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('escritorio'));

    $empresa = Empresa::where('cnpj', 'like', '22333444%')->first();
    $contrato = Contrato::where('situacao', SituacaoContrato::EmAnalise)->first();

    foreach ([SociosRelationManager::class, EstabelecimentosRelationManager::class, RotinaRelationManager::class, UsuariosRelationManager::class] as $rm) {
        Livewire::test($rm, ['ownerRecord' => $empresa, 'pageClass' => EditEmpresa::class])->assertOk();
    }
    foreach ([DocumentosRelationManager::class, HistoricoRelationManager::class] as $rm) {
        Livewire::test($rm, ['ownerRecord' => $contrato, 'pageClass' => ViewAdmissao::class])->assertOk();
    }
    Livewire::test(ArquivosLoteRelationManager::class, ['ownerRecord' => LoteImportacaoXml::first(), 'pageClass' => ViewLote::class])->assertOk();

    Livewire::test(IndicadoresEmpresas::class)->assertOk()->assertSee('Empresas devendo documentação');
    Livewire::test(EmpresasDevendoDocumentacao::class)->assertOk()->assertSee('Construtora Alicerce');
});

it('restringe o colaborador às permissões do perfil', function () {
    entrarComo($this->colaborador);

    $empresa = Empresa::where('cnpj', 'like', '22333444%')->first();

    foreach (['/admin', '/admin/empresas', '/admin/fila-de-avaliacao', '/admin/admissoes',
        "/admin/empresa/{$empresa->id}/notas-fiscais-entrada", "/admin/empresa/{$empresa->id}/cadastro"] as $pagina) {
        expect($this->get($pagina)->status())->toBe(200, "Falhou: {$pagina}");
    }

    // exclusivas do administrador (RN-223) e fora do perfil Colaborador (D4)
    foreach (['/admin/equipe', '/admin/configuracoes', '/admin/catalogo-documentos', '/admin/relatorios/admissoes', '/admin/acessos', '/admin/cobrancas'] as $pagina) {
        expect($this->get($pagina)->status())->toBe(403, "Deveria bloquear: {$pagina}");
    }

    // RN-1500 — colaborador não vê o dashboard
    Filament::setCurrentPanel(Filament::getPanel('escritorio'));
    expect(IndicadoresEmpresas::canView())->toBeFalse();
});

it('abre o portal da empresa e isola as empresas', function () {
    entrarComo($this->usuarioEmpresa);

    $demo = Empresa::where('cnpj', 'like', '22333444%')->first();
    $construtora = Empresa::withoutGlobalScopes()->where('cnpj', 'like', '44555666%')->first();
    // a própria consulta já esconde empresas não vinculadas (RN-240)
    expect(Empresa::where('cnpj', 'like', '44555666%')->exists())->toBeFalse();
    $rascunho = Contrato::where('empresa_id', $demo->id)->where('situacao', SituacaoContrato::Rascunho)->first();
    $emAnalise = Contrato::where('empresa_id', $demo->id)->where('situacao', SituacaoContrato::EmAnalise)->first();
    $lote = LoteImportacaoXml::where('empresa_id', $demo->id)->first();

    $paginas = [
        "/portal/{$demo->id}", "/portal/{$demo->id}/documentos-do-mes", "/portal/{$demo->id}/notas-fiscais",
        "/portal/{$demo->id}/envios-xml", "/portal/{$demo->id}/envios-xml/{$lote->id}",
        "/portal/{$demo->id}/admissoes/create", "/portal/{$demo->id}/admissoes/{$rascunho->id}/edit",
        "/portal/{$demo->id}/admissoes/{$emAnalise->id}", "/portal/{$demo->id}/funcionarios", "/portal/{$demo->id}/dados-da-empresa",
    ];

    foreach ($paginas as $pagina) {
        expect($this->get($pagina)->status())->toBe(200, "Falhou: {$pagina}");
    }

    // RN-588 — admissão solicitada não pode ser editada pela empresa
    expect($this->get("/portal/{$demo->id}/admissoes/{$emAnalise->id}/edit")->status())->toBe(403);
    // RN-240 — empresa não vinculada
    expect($this->get("/portal/{$construtora->id}")->status())->toBeIn([403, 404]);
    // usuário da empresa nunca acessa o painel do escritório
    expect($this->get('/admin')->status())->toBe(403);

    Filament::setCurrentPanel(Filament::getPanel('portal'));
    Filament::setTenant($demo);
    Livewire::test(ResumoDoMes::class)->assertOk();
});

it('isola os escritórios (RN-100)', function () {
    $outro = User::where('email', 'admin2@contab.test')->first();
    entrarComo($outro);

    expect(Empresa::count())->toBe(1)
        ->and(Empresa::first()->razao_social)->toBe('Mercadinho Horizonte Ltda');

    $empresaDoOutroEscritorio = Empresa::withoutGlobalScopes()->where('cnpj', 'like', '22333444%')->first();
    expect($this->get("/admin/empresas/{$empresaDoOutroEscritorio->id}/edit")->status())->toBe(404);
});

it('abre o contexto da empresa com o menu próprio (notas, funcionários, cadastro)', function () {
    entrarComo($this->admin);

    $empresa = Empresa::where('cnpj', 'like', '22333444%')->first();
    $base = "/admin/empresa/{$empresa->id}";
    $lote = LoteImportacaoXml::where('empresa_id', $empresa->id)->first();

    // selecionar a empresa abre a Página da Empresa (ações pendentes)
    $this->get($base)->assertOk()->assertSee('Página da Empresa');

    $pagina = $this->get("{$base}/notas-fiscais-entrada")->assertOk();
    foreach (['Notas Fiscais de Entrada', 'Notas Fiscais de Saída', 'Funcionários', 'Cadastro da Empresa'] as $item) {
        $pagina->assertSee($item);
    }
    // o menu do contexto não mistura o trabalho geral do escritório
    $pagina->assertDontSee('Fila de avaliação');
    // o logo "Contab Admin" volta para a tela inicial do escritório
    expect($pagina->getContent())->toMatch('#<a[^>]+href="'.preg_quote(url('/admin'), '#').'"#');

    foreach (["{$base}/notas-fiscais-saida", "{$base}/funcionarios", "{$base}/cadastro", "{$base}/envios-xml", "{$base}/envios-xml/{$lote->id}"] as $url) {
        expect($this->get($url)->status())->toBe(200, "Falhou: {$url}");
    }

    // entrada e saída separadas (RN-716)
    $this->get("{$base}/notas-fiscais-entrada")->assertSee('Fornecedor Atacadista')->assertDontSee('Cliente Consumidor');
    $this->get("{$base}/notas-fiscais-saida")->assertSee('Cliente Consumidor')->assertDontSee('Fornecedor Atacadista');

    // empresa de outro escritório não abre (RN-100)
    $outra = Empresa::withoutGlobalScopes()->where('razao_social', 'Mercadinho Horizonte Ltda')->first();
    expect($this->get("/admin/empresa/{$outra->id}/notas-fiscais-entrada")->status())->toBeIn([403, 404]);
});

it('não deixa o usuário da empresa entrar no contexto da empresa do escritório', function () {
    entrarComo($this->usuarioEmpresa);
    $empresa = Empresa::withoutGlobalScopes()->where('cnpj', 'like', '22333444%')->first();

    expect($this->get("/admin/empresa/{$empresa->id}/notas-fiscais-entrada")->status())->toBe(403);
});

it('abre o cadastro do funcionário dentro do contexto da empresa, com o menu da empresa', function () {
    entrarComo($this->admin);

    $contrato = Contrato::where('situacao', SituacaoContrato::Admitido)->first();
    $base = "/admin/empresa/{$contrato->empresa_id}";
    $outro = Contrato::where('empresa_id', '!=', $contrato->empresa_id)->first();

    $lista = $this->get("{$base}/funcionarios")->assertOk();
    expect($lista->getContent())->toContain(url("{$base}/funcionarios/cadastro/{$contrato->id}"));

    foreach (["{$base}/funcionarios/cadastro/{$contrato->id}", "{$base}/funcionarios/cadastro/{$contrato->id}/edit"] as $pagina) {
        $resposta = $this->get($pagina);
        expect($resposta->status())->toBe(200, "Falhou: {$pagina}");
        $resposta->assertSee('Notas Fiscais de Entrada')->assertSee('Despesas')->assertDontSee('Fila de admissões');
    }

    // contrato de outra empresa não abre no contexto desta
    expect($this->get("{$base}/funcionarios/cadastro/{$outro->id}")->status())->toBe(404);
});

it('soma as permissões dos perfis do colaborador (RN-220 a RN-224)', function () {
    $operacional = $this->colaborador; // só Operacional
    $acumula = User::where('email', 'daniela@contab.test')->first(); // Operacional + Financeiro
    $empresa = Empresa::where('cnpj', 'like', '22333444%')->first();

    setPermissionsTeamId($operacional->escritorio_id);
    expect(DefinirPerfis::perfisDe($operacional))->toBe([Perfil::Operacional])
        ->and($operacional->can('despesas.gerenciar'))->toBeFalse()
        ->and($acumula->can('despesas.gerenciar'))->toBeTrue()
        ->and($acumula->can('admissoes.analisar'))->toBeTrue()
        ->and($acumula->can('equipe.gerenciar'))->toBeFalse(); // exclusiva do administrador (RN-223)

    // colaborador precisa de ao menos um perfil
    expect(fn () => app(DefinirPerfis::class)->executar($operacional, []))
        ->toThrow(RegraDeNegocioException::class);

    entrarComo($acumula);
    expect($this->get('/admin/cobrancas')->status())->toBe(200)
        ->and($this->get("/admin/empresa/{$empresa->id}/despesas")->status())->toBe(200);
});

it('convida colaborador com mais de um perfil e altera os perfis pela tela da equipe', function () {
    entrarComo($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('escritorio'));

    Livewire::test(CreateUsuario::class)
        ->fillForm(['name' => 'Gabriel Finanças', 'email' => 'gabriel@contab.test', 'tipo' => 'colaborador', 'perfis' => ['Operacional', 'Financeiro']])
        ->call('create')->assertHasNoFormErrors();

    $gabriel = User::where('email', 'gabriel@contab.test')->first();
    expect(DefinirPerfis::perfisDe($gabriel))->toHaveCount(2);

    Livewire::test(EditUsuario::class, ['record' => $gabriel->id])
        ->assertFormSet(fn (array $state) => expect($state['perfis'])->toEqualCanonicalizing(['Operacional', 'Financeiro']) ? [] : [])
        ->fillForm(['perfis' => ['Financeiro']])->call('save')->assertHasNoFormErrors();

    expect(DefinirPerfis::perfisDe($gabriel->fresh()))->toBe([Perfil::Financeiro]);

    $this->get('/admin/equipe')->assertOk()->assertSee('Operacional')->assertSee('Financeiro')->assertSee('Acesso total');
});

it('fecha o painel de filtros ao aplicar os filtros', function () {
    entrarComo($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('escritorio'));

    $html = Livewire::test(ListFilaAvaliacao::class)->html();

    expect($html)->toMatch('/data-table-filter-action="apply"[^>]*x-on:click="\$wire\.applyTableFilters\(\); \$el\.closest\(\'\.fi-dropdown\'\) && close\(\)"/');
});
