<?php

use App\Actions\Despesas\ExcluirDespesa;
use App\Actions\Despesas\RegistrarPagamentoDespesa;
use App\Actions\Despesas\SalvarCategoriaDespesa;
use App\Actions\Despesas\SalvarConfigDocumentosDespesa;
use App\Actions\Despesas\SalvarDespesa;
use App\Exceptions\RegraDeNegocioException;
use App\Filament\Compartilhado\CampoMoeda;
use App\Filament\Empresa\Resources\Despesas\Pages\ManageDespesas;
use App\Filament\Escritorio\Resources\CategoriasDespesa\CategoriaDespesaResource;
use App\Filament\Portal\Resources\Despesas\Pages\ManageDespesas as PortalManageDespesas;
use App\Models\CategoriaDespesa;
use App\Models\Despesa;
use App\Models\Empresa;
use App\Models\User;
use Database\Seeders\DesenvolvimentoSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DesenvolvimentoSeeder::class);
    $this->colaborador = User::where('email', 'daniela@contab.test')->first(); // todas as atribuições
    $this->empresa = Empresa::withoutGlobalScopes()->where('cnpj', 'like', '22333444%')->first();
    $this->categoria = CategoriaDespesa::withoutGlobalScopes()->where('escritorio_id', $this->empresa->escritorio_id)->where('nome', 'Fornecedores')->first();
    setPermissionsTeamId($this->colaborador->escritorio_id);
});

it('cadastra, altera e exclui despesa da empresa (RN-1600 a RN-1603)', function () {
    $despesa = app(SalvarDespesa::class)->criar($this->empresa, $this->colaborador, [
        'categoria_despesa_id' => $this->categoria->id, 'descricao' => 'Conta de água', 'valor' => '150.30', 'data_vencimento' => '2026-11-10',
    ]);

    expect($despesa->empresa_id)->toBe($this->empresa->id)
        ->and($despesa->cadastrada_por_id)->toBe($this->colaborador->id)
        ->and($despesa->valor)->toBe('150.30');

    app(SalvarDespesa::class)->atualizar($despesa, $this->colaborador, ['categoria_despesa_id' => $this->categoria->id, 'descricao' => 'Conta de água e esgoto', 'valor' => '180', 'data_vencimento' => '2026-11-12']);
    expect($despesa->fresh()->descricao)->toBe('Conta de água e esgoto');

    app(ExcluirDespesa::class)->executar($despesa, $this->colaborador);
    expect(Despesa::find($despesa->id))->toBeNull()
        ->and(Despesa::withTrashed()->find($despesa->id))->not->toBeNull(); // exclusão lógica
});

it('cadastra e edita pela tela da empresa', function () {
    $this->actingAs($this->colaborador);
    Filament::setCurrentPanel(Filament::getPanel('empresa'));
    Filament::setTenant($this->empresa);

    Livewire::test(ManageDespesas::class)
        ->callAction('create', ['categoria_despesa_id' => $this->categoria->id, 'descricao' => 'Seguro do veículo', 'valor' => '320.50', 'data_vencimento' => '2026-11-20'])
        ->assertHasNoActionErrors();

    $despesa = Despesa::where('descricao', 'Seguro do veículo')->firstOrFail();
    expect($despesa->empresa_id)->toBe($this->empresa->id);

    Livewire::test(ManageDespesas::class)
        ->callTableAction('edit', $despesa, ['categoria_despesa_id' => $this->categoria->id, 'descricao' => 'Seguro do veículo (anual)', 'valor' => '330', 'data_vencimento' => '2026-11-20'])
        ->assertHasNoTableActionErrors();
    expect($despesa->fresh()->valor)->toBe('330.00');

    Livewire::test(ManageDespesas::class)->callTableAction('delete', $despesa);
    expect(Despesa::find($despesa->id))->toBeNull();
});

it('não aceita valor zero ou negativo (RN-1601)', function () {
    app(SalvarDespesa::class)->criar($this->empresa, $this->colaborador, ['categoria_despesa_id' => $this->categoria->id, 'descricao' => 'Teste', 'valor' => '0', 'data_vencimento' => '2026-11-10']);
})->throws(RegraDeNegocioException::class);

it('isola despesas por empresa e por escritório', function () {
    $outroAdmin = User::where('email', 'admin2@contab.test')->first();

    expect(fn () => app(SalvarDespesa::class)->criar($this->empresa, $outroAdmin, ['categoria_despesa_id' => $this->categoria->id, 'descricao' => 'X', 'valor' => '10', 'data_vencimento' => '2026-11-10']))
        ->toThrow(RegraDeNegocioException::class);

    $outra = Empresa::withoutGlobalScopes()->where('cnpj', 'like', '33444555%')->first();
    expect($outra->despesas()->count())->toBe(5);

    // o Filament filtra a lista pela empresa da URL (tenant)
    $this->actingAs($this->colaborador);
    $this->get("/admin/empresa/{$this->empresa->id}/despesas")->assertOk()
        ->assertSee('Aluguel do imóvel')->assertSee('Despesas');
    expect(Despesa::query()->pluck('empresa_id')->unique()->all())->toBe([$this->empresa->id]);

    // usuário da empresa não acessa o painel do escritório
    $this->actingAs(User::where('email', 'empresa@contab.test')->first());
    $this->get("/admin/empresa/{$this->empresa->id}/despesas")->assertForbidden();
});

it('converte o valor com máscara para decimal', function () {
    expect(CampoMoeda::paraDecimal('1.234,56'))->toBe('1234.56')
        ->and(CampoMoeda::paraDecimal('600,00'))->toBe('600.00')
        ->and(CampoMoeda::paraDecimal('600'))->toBe('600.00')
        ->and(CampoMoeda::paraDecimal(''))->toBeNull();
});

it('cadastra com máscara, categoria e documentos, e registra o pagamento pela tela (RN-1605, RN-1606, RN-1609)', function () {
    Storage::fake('local');
    $this->actingAs($this->colaborador);
    Filament::setCurrentPanel(Filament::getPanel('empresa'));
    Filament::setTenant($this->empresa);

    Livewire::test(ManageDespesas::class)
        ->callAction('create', [
            'categoria_despesa_id' => $this->categoria->id, 'descricao' => 'Condomínio', 'valor' => '1.250,75', 'data_vencimento' => '2026-11-10',
            'documentos' => [
                ['tipo' => 'boleto', 'arquivo' => [UploadedFile::fake()->create('boleto-condominio.pdf', 50, 'application/pdf')]],
                ['tipo' => 'recibo', 'arquivo' => [UploadedFile::fake()->image('recibo.jpg')]],
            ],
        ])
        ->assertHasNoActionErrors();

    $despesa = Despesa::where('descricao', 'Condomínio')->firstOrFail();
    $documentos = $despesa->documentos()->orderBy('id')->get();
    expect($despesa->valor)->toBe('1250.75')
        ->and($despesa->categoria_despesa_id)->toBe($this->categoria->id)
        ->and($documentos->pluck('tipo')->all())->toBe(['boleto', 'recibo'])
        ->and($documentos[0]->nome_original)->toBe('boleto-condominio.pdf');
    Storage::disk('local')->assertExists($documentos[0]->arquivo);

    Livewire::test(ManageDespesas::class)
        ->callTableAction('pagar', $despesa, ['valor_pago' => '1.250,75', 'data_pagamento' => today()->toDateString()])
        ->assertHasNoTableActionErrors();

    $despesa->refresh();
    expect($despesa->estaPaga())->toBeTrue()
        ->and($despesa->valor_pago)->toBe('1250.75')
        ->and($despesa->pagamento_registrado_por_id)->toBe($this->colaborador->id);

    // não registra duas vezes nem com data futura
    expect(fn () => app(RegistrarPagamentoDespesa::class)->executar($despesa, $this->colaborador, '10', today()->toDateString()))
        ->toThrow(RegraDeNegocioException::class);
    $outra = $this->empresa->despesas()->whereNull('data_pagamento')->first();
    expect(fn () => app(RegistrarPagamentoDespesa::class)->executar($outra, $this->colaborador, '10', today()->addDay()->toDateString()))
        ->toThrow(RegraDeNegocioException::class);
});

it('cadastra despesa já paga pela tela do escritório (RN-1608)', function () {
    $this->actingAs($this->colaborador);
    Filament::setCurrentPanel(Filament::getPanel('empresa'));
    Filament::setTenant($this->empresa);

    Livewire::test(ManageDespesas::class)
        ->callAction('create', [
            'categoria_despesa_id' => $this->categoria->id, 'descricao' => 'Material de escritório', 'valor' => '80,00', 'data_vencimento' => today()->subDay()->toDateString(),
            'paga' => true, 'valor_pago' => '78,50', 'data_pagamento' => today()->toDateString(),
        ])
        ->assertHasNoActionErrors();

    $despesa = Despesa::where('descricao', 'Material de escritório')->firstOrFail();
    expect($despesa->estaPaga())->toBeTrue()
        ->and($despesa->valor_pago)->toBe('78.50')
        ->and($despesa->pagamento_registrado_por_id)->toBe($this->colaborador->id);
});

it('não cadastra despesa paga com data de pagamento futura (RN-1605, RN-1608)', function () {
    $antes = Despesa::withoutGlobalScopes()->count();

    expect(fn () => app(SalvarDespesa::class)->criar($this->empresa, $this->colaborador, [
        'categoria_despesa_id' => $this->categoria->id, 'descricao' => 'Teste', 'valor' => '10', 'data_vencimento' => '2026-11-10',
        'paga' => true, 'valor_pago' => '10', 'data_pagamento' => today()->addDay()->toDateString(),
    ]))->toThrow(RegraDeNegocioException::class);

    expect(Despesa::withoutGlobalScopes()->count())->toBe($antes); // nada gravado
});

it('usuário da empresa cadastra e paga as próprias despesas pelo portal (RN-1607)', function () {
    $carla = User::where('email', 'empresa@contab.test')->first();
    $this->actingAs($carla);

    $this->get("/portal/{$this->empresa->id}/despesas")->assertOk()->assertSee('Aluguel do imóvel');

    Filament::setCurrentPanel(Filament::getPanel('portal'));
    Filament::setTenant($this->empresa);

    Livewire::test(PortalManageDespesas::class)
        ->callAction('create', [
            'categoria_despesa_id' => $this->categoria->id, 'descricao' => 'Conta de luz', 'valor' => '210,00', 'data_vencimento' => today()->toDateString(),
            'paga' => true, 'valor_pago' => '210,00', 'data_pagamento' => today()->toDateString(),
        ])
        ->assertHasNoActionErrors();

    $despesa = Despesa::where('descricao', 'Conta de luz')->firstOrFail();
    expect($despesa->empresa_id)->toBe($this->empresa->id)
        ->and($despesa->cadastrada_por_id)->toBe($carla->id)
        ->and($despesa->estaPaga())->toBeTrue();
});

it('usuário da empresa não mexe em despesas de empresa a que não está vinculado (RN-240)', function () {
    $carla = User::where('email', 'empresa@contab.test')->first();
    $alheia = Empresa::withoutGlobalScopes()->where('cnpj', 'like', '44555666%')->first(); // Construtora
    $despesa = $alheia->despesas()->create(['escritorio_id' => $alheia->escritorio_id, 'descricao' => 'X', 'valor' => 10, 'data_vencimento' => today()]);

    expect(fn () => app(SalvarDespesa::class)->criar($alheia, $carla, ['categoria_despesa_id' => $this->categoria->id, 'descricao' => 'X', 'valor' => '10', 'data_vencimento' => '2026-11-10']))
        ->toThrow(RegraDeNegocioException::class)
        ->and(fn () => app(RegistrarPagamentoDespesa::class)->executar($despesa, $carla, '10', today()->toDateString()))
        ->toThrow(RegraDeNegocioException::class)
        ->and(fn () => app(ExcluirDespesa::class)->executar($despesa, $carla))
        ->toThrow(RegraDeNegocioException::class);

    $this->actingAs($carla)->get("/portal/{$alheia->id}/despesas")->assertNotFound();
});

it('exige categoria ativa do próprio escritório (RN-1609)', function () {
    $dados = ['descricao' => 'Sem categoria', 'valor' => '10', 'data_vencimento' => '2026-11-10'];

    expect(fn () => app(SalvarDespesa::class)->criar($this->empresa, $this->colaborador, $dados))
        ->toThrow(RegraDeNegocioException::class, 'categoria');

    $this->categoria->update(['ativo' => false]);
    expect(fn () => app(SalvarDespesa::class)->criar($this->empresa, $this->colaborador, $dados + ['categoria_despesa_id' => $this->categoria->id]))
        ->toThrow(RegraDeNegocioException::class, 'inativa');

    // despesa antiga mantém a categoria inativada ao ser alterada
    $antiga = Despesa::withoutGlobalScopes()->where('categoria_despesa_id', $this->categoria->id)->first();
    app(SalvarDespesa::class)->atualizar($antiga, $this->colaborador, ['descricao' => $antiga->descricao.' (ajuste)', 'valor' => $antiga->valor,
        'data_vencimento' => $antiga->data_vencimento->toDateString(), 'categoria_despesa_id' => $this->categoria->id]);
    expect($antiga->fresh()->descricao)->toEndWith('(ajuste)');
});

it('recusa documento fora do formato ou acima do tamanho configurado (RN-1610)', function () {
    Storage::fake('local');
    $admin = User::where('email', 'admin@contab.test')->first();
    app(SalvarConfigDocumentosDespesa::class)->executar($admin, ['cheque' => ['formatos' => ['imagem'], 'tamanho_maximo_mb' => 1]]);

    Storage::disk('local')->put('tmp/cheque.pdf', '%PDF-1.4 cheque');
    Storage::disk('local')->put('tmp/cheque.jpg', str_repeat('x', 2 * 1024 * 1024));
    $dados = ['descricao' => 'Pagamento em cheque', 'valor' => '50', 'data_vencimento' => '2026-11-10', 'categoria_despesa_id' => $this->categoria->id];

    expect(fn () => app(SalvarDespesa::class)->criar($this->empresa, $this->colaborador, $dados + ['documentos' => [['tipo' => 'cheque', 'arquivo' => 'tmp/cheque.pdf']]]))
        ->toThrow(RegraDeNegocioException::class, 'formato')
        ->and(fn () => app(SalvarDespesa::class)->criar($this->empresa, $this->colaborador, $dados + ['documentos' => [['tipo' => 'cheque', 'arquivo' => 'tmp/cheque.jpg']]]))
        ->toThrow(RegraDeNegocioException::class, 'limite');

    expect(Despesa::where('descricao', 'Pagamento em cheque')->exists())->toBeFalse(); // nada gravado
});

it('só o administrador cadastra categorias e configura os documentos (RN-1609, RN-1610)', function () {
    $admin = User::where('email', 'admin@contab.test')->first();

    expect(fn () => app(SalvarCategoriaDespesa::class)->criar($this->colaborador, 'Viagens'))->toThrow(RegraDeNegocioException::class)
        ->and(fn () => app(SalvarConfigDocumentosDespesa::class)->executar($this->colaborador, ['boleto' => ['formatos' => ['pdf'], 'tamanho_maximo_mb' => 5]]))
        ->toThrow(RegraDeNegocioException::class);

    $viagens = app(SalvarCategoriaDespesa::class)->criar($admin, 'Viagens');
    expect($viagens->escritorio_id)->toBe($admin->escritorio_id)
        ->and(fn () => app(SalvarCategoriaDespesa::class)->criar($admin, 'viagens'))->toThrow(RegraDeNegocioException::class, 'Já existe');

    $this->actingAs($admin)->get('/admin/categorias-despesa')->assertOk()->assertSee('Viagens');
    $this->get('/admin/configuracoes')->assertOk()->assertSee('Documentos das despesas')->assertSee('Nota Fiscal');
    $this->actingAs($this->colaborador);
    expect(CategoriaDespesaResource::canViewAny())->toBeFalse();
});

it('mostra as ações da lista de despesas só com ícone, inclusive editar e excluir', function () {
    $this->actingAs($this->colaborador);
    Filament::setCurrentPanel(Filament::getPanel('empresa'));
    Filament::setTenant($this->empresa);

    $html = Livewire::test(ManageDespesas::class)->html();

    expect($html)->toContain('Editar: Altera os dados do registro.')
        ->and($html)->toContain('Excluir: Exclui o registro');
});
