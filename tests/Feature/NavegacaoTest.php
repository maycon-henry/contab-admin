<?php

use App\Actions\Usuarios\DefinirAtribuicoes;
use App\Enums\Atribuicao;
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

it('restringe o colaborador às permissões das suas atribuições', function () {
    entrarComo($this->colaborador);

    $empresa = Empresa::where('cnpj', 'like', '22333444%')->first();

    foreach (['/admin', '/admin/empresas', '/admin/fila-de-avaliacao', '/admin/admissoes',
        "/admin/empresa/{$empresa->id}/notas-fiscais", "/admin/empresa/{$empresa->id}/cadastro"] as $pagina) {
        expect($this->get($pagina)->status())->toBe(200, "Falhou: {$pagina}");
    }

    // exclusivas do administrador (RN-223) e atribuições que ele não tem
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

    // o histórico de envios fica dentro da tela de Notas Fiscais, não no menu
    $this->get("/portal/{$demo->id}/notas-fiscais")->assertOk()
        ->assertSee('Enviar XMLs')->assertSee('Histórico de envios')->assertSee("/portal/{$demo->id}/envios-xml", false)
        ->assertDontSee('Envios de XML');

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

    $pagina = $this->get("{$base}/notas-fiscais")->assertOk();
    foreach (['Notas Fiscais', 'Funcionários', 'Cadastro da Empresa'] as $item) {
        $pagina->assertSee($item);
    }
    // entrada e saída numa lista só: não há mais um item de menu para cada
    $pagina->assertDontSee('Notas Fiscais de Entrada')->assertDontSee('Notas Fiscais de Saída');
    // o menu do contexto não mistura o trabalho geral do escritório
    $pagina->assertDontSee('Fila de avaliação');
    // o logo "Contab Admin" volta para a tela inicial do escritório
    expect($pagina->getContent())->toMatch('#<a[^>]+href="'.preg_quote(url('/admin'), '#').'"#');

    foreach (["{$base}/funcionarios", "{$base}/cadastro", "{$base}/envios-xml", "{$base}/envios-xml/{$lote->id}"] as $url) {
        expect($this->get($url)->status())->toBe(200, "Falhou: {$url}");
    }

    // a lista traz entradas e saídas; as abas separam (RN-716)
    $this->get("{$base}/notas-fiscais")->assertSee('Fornecedor Atacadista')->assertSee('Cliente Consumidor');
    $this->get("{$base}/notas-fiscais?tab=entrada")->assertSee('Fornecedor Atacadista')->assertDontSee('Cliente Consumidor');
    $this->get("{$base}/notas-fiscais?tab=saida")->assertSee('Cliente Consumidor')->assertDontSee('Fornecedor Atacadista');

    // endereços antigos continuam funcionando
    $this->get("{$base}/notas-fiscais-entrada")->assertRedirect("{$base}/notas-fiscais?tab=entrada");
    $this->get("{$base}/notas-fiscais-saida")->assertRedirect("{$base}/notas-fiscais?tab=saida");

    // empresa de outro escritório não abre (RN-100)
    $outra = Empresa::withoutGlobalScopes()->where('razao_social', 'Mercadinho Horizonte Ltda')->first();
    expect($this->get("/admin/empresa/{$outra->id}/notas-fiscais")->status())->toBeIn([403, 404]);
});

it('não deixa o usuário da empresa entrar no contexto da empresa do escritório', function () {
    entrarComo($this->usuarioEmpresa);
    $empresa = Empresa::withoutGlobalScopes()->where('cnpj', 'like', '22333444%')->first();

    expect($this->get("/admin/empresa/{$empresa->id}/notas-fiscais")->status())->toBe(403);
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
        $resposta->assertSee('Notas Fiscais')->assertSee('Despesas')->assertDontSee('Fila de admissões');
    }

    // contrato de outra empresa não abre no contexto desta
    expect($this->get("{$base}/funcionarios/cadastro/{$outro->id}")->status())->toBe(404);
});

it('soma as permissões das atribuições do colaborador (RN-220 a RN-224)', function () {
    $operacional = $this->colaborador; // empresas, portal, admissões, documentos e notas
    $tudo = User::where('email', 'daniela@contab.test')->first(); // todas as atribuições
    $empresa = Empresa::where('cnpj', 'like', '22333444%')->first();

    setPermissionsTeamId($operacional->escritorio_id);
    expect(DefinirAtribuicoes::atribuicoesDe($operacional))->toContain(Atribuicao::Admissoes)->not->toContain(Atribuicao::Despesas)
        ->and($operacional->can('despesas.gerenciar'))->toBeFalse()
        ->and($operacional->can('admissoes.analisar'))->toBeTrue()
        ->and($tudo->can('despesas.gerenciar'))->toBeTrue()
        ->and($tudo->can('funcionarios.dados_sensiveis'))->toBeTrue()
        ->and($tudo->can('catalogo.gerenciar'))->toBeTrue()
        ->and($tudo->can('equipe.gerenciar'))->toBeFalse() // exclusiva do administrador (RN-223)
        ->and($tudo->can('escritorio.configurar'))->toBeFalse();

    // colaborador precisa de ao menos uma atribuição
    expect(fn () => app(DefinirAtribuicoes::class)->executar($operacional, []))
        ->toThrow(RegraDeNegocioException::class);

    // só financeiro e despesas: cobra e lança despesas, mas não analisa admissões
    app(DefinirAtribuicoes::class)->executar($operacional, [Atribuicao::Cobranca, Atribuicao::Despesas]);
    expect($operacional->fresh()->can('admissoes.analisar'))->toBeFalse()
        ->and($operacional->fresh()->can('empresas.visualizar'))->toBeTrue(); // consulta básica de todo colaborador

    entrarComo($tudo);
    expect($this->get('/admin/cobrancas')->status())->toBe(200)
        ->and($this->get('/admin/catalogo-documentos')->status())->toBe(200)
        ->and($this->get('/admin/relatorios/admissoes')->status())->toBe(200)
        ->and($this->get("/admin/empresa/{$empresa->id}/despesas")->status())->toBe(200)
        ->and($this->get('/admin/equipe')->status())->toBe(403);
});

it('convida colaborador com atribuições e altera as atribuições pela tela da equipe', function () {
    entrarComo($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('escritorio'));

    Livewire::test(CreateUsuario::class)
        ->fillForm(['name' => 'Gabriel Admissões', 'email' => 'gabriel@contab.test', 'tipo' => 'colaborador', 'atribuicoes' => ['admissoes']])
        ->call('create')->assertHasNoFormErrors();

    $gabriel = User::where('email', 'gabriel@contab.test')->first();
    expect(DefinirAtribuicoes::atribuicoesDe($gabriel))->toBe([Atribuicao::Admissoes]);

    Livewire::test(EditUsuario::class, ['record' => $gabriel->id])
        ->assertFormSet(['atribuicoes' => [Atribuicao::Admissoes]])
        ->fillForm(['atribuicoes' => ['cobranca', 'despesas']])->call('save')->assertHasNoFormErrors();

    expect(DefinirAtribuicoes::atribuicoesDe($gabriel->fresh()))->toBe([Atribuicao::Cobranca, Atribuicao::Despesas]);

    $this->get('/admin/equipe')->assertOk()
        ->assertSee('Admissões e desligamentos')->assertSee('Todas as atribuições')->assertSee('Acesso total');
});

it('fecha o painel de filtros ao aplicar os filtros', function () {
    entrarComo($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('escritorio'));

    $html = Livewire::test(ListFilaAvaliacao::class)->html();

    expect($html)->toMatch('/data-table-filter-action="apply"[^>]*x-on:click="\$wire\.applyTableFilters\(\); \$el\.closest\(\'\.fi-dropdown\'\) && close\(\)"/');
});

it('mostra as ações das linhas só com ícone, com nome e descrição no tooltip', function () {
    entrarComo($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('escritorio'));

    $html = Livewire::test(ListFilaAvaliacao::class)->html();

    expect($html)->toContain('fi-icon-btn')
        ->and($html)->toContain('Aceitar: Aceita o documento como correto e válido.')
        ->and($html)->toContain('Recusar: Recusa o documento e avisa a empresa do motivo.')
        ->and($html)->not->toMatch('/<a[^>]*fi-link[^>]*>\s*(<[^>]+>\s*)*Arquivos e histórico/');
});

it('mostra as janelas informativas em tabela, com cabeçalho e colunas separadas', function () {
    entrarComo($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('escritorio'));
    $padaria = Empresa::where('cnpj', 'like', '33444555%')->first();

    Livewire::test(EmpresasDevendoDocumentacao::class)
        ->mountTableAction('quais', $padaria)
        ->assertMountedActionModalSeeHtml(['<thead', 'Competência', 'Atraso', 'dia(s)']);
});
