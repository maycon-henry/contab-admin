<?php

use App\Domain\Consultas\PendenciasDaEmpresa;
use App\Enums\SituacaoContrato;
use App\Enums\SituacaoItem;
use App\Filament\Empresa\Widgets\AcoesPendentes;
use App\Filament\Empresa\Widgets\ResumoPendencias;
use App\Models\Contrato;
use App\Models\Empresa;
use App\Models\User;
use Database\Seeders\DesenvolvimentoSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DesenvolvimentoSeeder::class);
    $this->admin = User::where('email', 'admin@contab.test')->first();
    setPermissionsTeamId($this->admin->escritorio_id);
    $this->empresa = Empresa::withoutGlobalScopes()->where('cnpj', 'like', '22333444%')->first();
});

it('reúne as pendências da empresa por assunto (RN-1520 a RN-1523)', function () {
    $vencida = $this->empresa->despesas()->create(['escritorio_id' => $this->empresa->escritorio_id, 'descricao' => 'Água atrasada', 'valor' => 90, 'data_vencimento' => today()->subDays(3)]);
    $paga = $this->empresa->despesas()->create(['escritorio_id' => $this->empresa->escritorio_id, 'descricao' => 'Luz paga', 'valor' => 50, 'data_vencimento' => today()->subDays(3), 'valor_pago' => 50, 'data_pagamento' => today()]);
    $futura = $this->empresa->despesas()->create(['escritorio_id' => $this->empresa->escritorio_id, 'descricao' => 'Daqui a um mês', 'valor' => 10, 'data_vencimento' => today()->addDays(30)]);

    $p = PendenciasDaEmpresa::para($this->empresa);

    expect($p->despesasVencidas()->pluck('id'))->toContain($vencida->id)->not->toContain($paga->id)
        ->and($p->despesasAVencer()->pluck('id'))->not->toContain($futura->id)
        ->and($p->movimentosDeFuncionarios()->pluck('situacao')->map->value->unique()->all())
        ->each->toBeIn(['rascunho', 'devolvida', 'em_analise', 'em_desligamento'])
        ->and($p->documentosDaEmpresa()->get()->every(fn ($i) => in_array($i->situacao->value, ['pendente', 'recusado'])))->toBeTrue();
});

it('abre a Página da Empresa pelo botão da lista de empresas', function () {
    $this->actingAs($this->admin);
    $url = url("/admin/empresa/{$this->empresa->id}");

    $this->get("/admin/empresa/{$this->empresa->id}")->assertOk()
        ->assertSee('Página da Empresa')->assertSee('Notas Fiscais');

    // (o Filament guarda o menu do painel no processo; por isso a lista do escritório vem depois)
    $this->get('/admin/empresas')->assertOk()->assertSee('Página da Empresa')->assertDontSee('Abrir empresa')
        ->assertSee($url, false);

    Filament::setCurrentPanel(Filament::getPanel('empresa'));
    Filament::setTenant($this->empresa);

    Livewire::test(ResumoPendencias::class)->assertOk()
        ->assertSee('Documentos devidos pela empresa')->assertSee('Contas vencidas e não pagas');

    $emAnalise = Contrato::where('empresa_id', $this->empresa->id)->where('situacao', SituacaoContrato::EmAnalise)->with('funcionario')->first();
    $despesa = $this->empresa->despesas()->create(['escritorio_id' => $this->empresa->escritorio_id, 'descricao' => 'Água atrasada', 'valor' => 90, 'data_vencimento' => today()->subDays(2)]);

    $componente = Livewire::test(AcoesPendentes::class)->assertOk()
        ->assertSee('Ações pendentes')->assertSee('Água atrasada')->assertSee('Quem age')
        ->assertTableActionVisible('pagar', 'despesa-'.$despesa->id)
        ->assertTableActionHidden('cobrar', 'despesa-'.$despesa->id);
    if ($emAnalise) {
        $componente->assertSee($emAnalise->funcionario->nome)->assertTableActionVisible('cadastro', 'funcionario-'.$emAnalise->id);
    }

    // o botão da última coluna resolve a pendência: registra o pagamento ali mesmo
    $componente->callTableAction('pagar', 'despesa-'.$despesa->id, ['valor_pago' => '90,00', 'data_pagamento' => today()->toDateString()])
        ->assertHasNoTableActionErrors();
    expect($despesa->fresh()->estaPaga())->toBeTrue();
});

it('escritório envia o documento devido pela Página da Empresa e ele já fica aceito (RN-646)', function () {
    // preparação antes das requisições (o filtro de tenant fica ativo depois — AGENTS.md seção 11)
    $item = PendenciasDaEmpresa::para($this->empresa)->documentosDaEmpresa()->withoutGlobalScopes()
        ->where('nome', 'Extrato bancário')->first(); // aceita PDF (as notas fiscais só aceitam XML)
    expect($item)->not->toBeNull();

    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('empresa'));
    Filament::setTenant($this->empresa);

    Livewire::test(AcoesPendentes::class)
        ->assertTableActionVisible('enviar', 'documento_empresa-'.$item->id)
        ->callTableAction('enviar', 'documento_empresa-'.$item->id, ['arquivos' => [UploadedFile::fake()->create('extrato.pdf', 20, 'application/pdf')]])
        ->assertHasNoTableActionErrors();

    expect($item->fresh()->situacao)->toBe(SituacaoItem::Aceito);
});
