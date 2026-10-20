<?php

use App\Actions\Despesas\ExcluirDespesa;
use App\Actions\Despesas\RegistrarPagamentoDespesa;
use App\Actions\Despesas\SalvarDespesa;
use App\Exceptions\RegraDeNegocioException;
use App\Filament\Compartilhado\CampoMoeda;
use App\Filament\Empresa\Resources\Despesas\Pages\ManageDespesas;
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
    $this->colaborador = User::where('email', 'daniela@contab.test')->first(); // perfis Operacional + Financeiro
    $this->empresa = Empresa::withoutGlobalScopes()->where('cnpj', 'like', '22333444%')->first();
    setPermissionsTeamId($this->colaborador->escritorio_id);
});

it('cadastra, altera e exclui despesa da empresa (RN-1600 a RN-1603)', function () {
    $despesa = app(SalvarDespesa::class)->criar($this->empresa, $this->colaborador, [
        'descricao' => 'Conta de água', 'valor' => '150.30', 'data_vencimento' => '2026-11-10',
    ]);

    expect($despesa->empresa_id)->toBe($this->empresa->id)
        ->and($despesa->cadastrada_por_id)->toBe($this->colaborador->id)
        ->and($despesa->valor)->toBe('150.30');

    app(SalvarDespesa::class)->atualizar($despesa, $this->colaborador, ['descricao' => 'Conta de água e esgoto', 'valor' => '180', 'data_vencimento' => '2026-11-12']);
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
        ->callAction('create', ['descricao' => 'Seguro do veículo', 'valor' => '320.50', 'data_vencimento' => '2026-11-20'])
        ->assertHasNoActionErrors();

    $despesa = Despesa::where('descricao', 'Seguro do veículo')->firstOrFail();
    expect($despesa->empresa_id)->toBe($this->empresa->id);

    Livewire::test(ManageDespesas::class)
        ->callTableAction('edit', $despesa, ['descricao' => 'Seguro do veículo (anual)', 'valor' => '330', 'data_vencimento' => '2026-11-20'])
        ->assertHasNoTableActionErrors();
    expect($despesa->fresh()->valor)->toBe('330.00');

    Livewire::test(ManageDespesas::class)->callTableAction('delete', $despesa);
    expect(Despesa::find($despesa->id))->toBeNull();
});

it('não aceita valor zero ou negativo (RN-1601)', function () {
    app(SalvarDespesa::class)->criar($this->empresa, $this->colaborador, ['descricao' => 'Teste', 'valor' => '0', 'data_vencimento' => '2026-11-10']);
})->throws(RegraDeNegocioException::class);

it('isola despesas por empresa e por escritório', function () {
    $outroAdmin = User::where('email', 'admin2@contab.test')->first();

    expect(fn () => app(SalvarDespesa::class)->criar($this->empresa, $outroAdmin, ['descricao' => 'X', 'valor' => '10', 'data_vencimento' => '2026-11-10']))
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

it('cadastra com máscara e boleto, e registra o pagamento pela tela (RN-1605, RN-1606)', function () {
    Storage::fake('local');
    $this->actingAs($this->colaborador);
    Filament::setCurrentPanel(Filament::getPanel('empresa'));
    Filament::setTenant($this->empresa);

    $boleto = UploadedFile::fake()->create('boleto-condominio.pdf', 50, 'application/pdf');

    Livewire::test(ManageDespesas::class)
        ->callAction('create', ['descricao' => 'Condomínio', 'valor' => '1.250,75', 'data_vencimento' => '2026-11-10', 'boleto_caminho' => $boleto])
        ->assertHasNoActionErrors();

    $despesa = Despesa::where('descricao', 'Condomínio')->firstOrFail();
    expect($despesa->valor)->toBe('1250.75')
        ->and($despesa->boleto_nome)->toBe('boleto-condominio.pdf');
    Storage::disk('local')->assertExists($despesa->boleto_caminho);

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
