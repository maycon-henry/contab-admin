<?php

use App\Contracts\GeradorDanfe;
use App\Enums\SituacaoNota;
use App\Filament\Empresa\Resources\NotasFiscais\Pages\ListNotasFiscais as ListNotasDaEmpresa;
use App\Filament\Portal\Resources\NotasFiscais\Pages\ListNotasFiscais;
use App\Models\Empresa;
use App\Models\NotaFiscal;
use App\Models\User;
use Database\Seeders\DesenvolvimentoSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DesenvolvimentoSeeder::class);
    $this->admin = User::where('email', 'admin@contab.test')->first();
    $this->colaborador = User::where('email', 'colaborador@contab.test')->first();
    $this->carla = User::where('email', 'empresa@contab.test')->first(); // Empresa Demo e Padaria
    setPermissionsTeamId($this->admin->escritorio_id);

    $this->demo = Empresa::withoutGlobalScopes()->where('cnpj', 'like', '22333444%')->first();
    $this->notaDemo = NotaFiscal::withoutGlobalScopes()->where('empresa_id', $this->demo->id)->where('modelo', '55')
        ->where('situacao', SituacaoNota::Autorizada)->first();
});

it('abre o DANFE da nota a partir do XML (RN-743)', function () {
    $resposta = $this->actingAs($this->colaborador)->get(route('notas.danfe', $this->notaDemo));

    $resposta->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($resposta->headers->get('Content-Disposition'))->toStartWith('inline;')
        ->and(substr($resposta->getContent(), 0, 5))->toBe('%PDF-');
});

it('gera também a DANFCE da NFC-e e marca a nota cancelada (RN-730)', function () {
    $nfce = NotaFiscal::withoutGlobalScopes()->where('modelo', '65')->first();
    $cancelada = NotaFiscal::withoutGlobalScopes()->where('situacao', SituacaoNota::Cancelada)->first();
    expect($nfce)->not->toBeNull()->and($cancelada)->not->toBeNull();

    $this->actingAs($this->admin)->get(route('notas.danfe', $nfce))->assertOk()->assertHeader('Content-Type', 'application/pdf');

    $gerador = Mockery::mock(GeradorDanfe::class);
    $gerador->shouldReceive('gerar')->once()->withArgs(fn ($xml, $modelo, $marcarCancelada) => $marcarCancelada === true)->andReturn('%PDF-fake');
    app()->instance(GeradorDanfe::class, $gerador);

    $this->get(route('notas.danfe', $cancelada))->assertOk();
});

it('usuário da empresa vê só as notas das suas empresas (RN-744)', function () {
    $construtora = Empresa::withoutGlobalScopes()->where('cnpj', 'like', '44555666%')->first(); // Carla não tem vínculo
    $alheia = $this->notaDemo->replicate();
    $alheia->forceFill(['empresa_id' => $construtora->id, 'chave_acesso' => str_repeat('9', 44)])->save();
    $outroEscritorio = User::where('email', 'admin2@contab.test')->first();

    $this->actingAs($this->carla)->get(route('notas.danfe', $this->notaDemo))->assertOk();
    $this->actingAs($this->carla)->get(route('notas.danfe', $alheia))->assertNotFound();

    // RN-100 — outro escritório não enxerga a nota
    $this->actingAs($outroEscritorio)->get(route('notas.danfe', $this->notaDemo))->assertNotFound();
});

it('exige login para ver a nota', function () {
    $this->get(route('notas.danfe', $this->notaDemo))->assertRedirect();
});

it('mostra o botão "Ver nota" ao lado do XML no portal e no painel da empresa', function () {
    $saida = NotaFiscal::withoutGlobalScopes()->where('empresa_id', $this->demo->id)->where('classificacao', 'saida')->first();

    $this->actingAs($this->carla);
    Filament::setCurrentPanel(Filament::getPanel('portal'));
    Filament::setTenant($this->demo);
    Livewire::test(ListNotasFiscais::class)
        ->assertTableActionVisible('danfe', $this->notaDemo)
        ->assertTableActionHasUrl('danfe', route('notas.danfe', $this->notaDemo), $this->notaDemo);

    $this->actingAs($this->colaborador);
    Filament::setCurrentPanel(Filament::getPanel('empresa'));
    Filament::setTenant($this->demo);
    Livewire::test(ListNotasDaEmpresa::class)->assertTableActionVisible('danfe', $saida);
});
