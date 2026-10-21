<?php

use App\Exceptions\RegraDeNegocioException;
use App\Filament\Empresa\Pages\CadastroEmpresa;
use App\Models\Empresa;
use App\Models\User;
use Database\Seeders\DesenvolvimentoSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DesenvolvimentoSeeder::class);
    $this->admin = User::where('email', 'admin@contab.test')->first();
    setPermissionsTeamId($this->admin->escritorio_id);
    $this->clinica = Empresa::withoutGlobalScopes()->where('cnpj', 'like', '55666777%')->first();
});

it('guarda o honorário acordado com vigência desde o início do atendimento (RN-1200)', function () {
    $vigente = $this->clinica->honorarios()->first();

    expect($this->clinica->honorario_valor)->toBe('1800.00')
        ->and($vigente->valor)->toBe('1800.00')
        ->and($vigente->dia_vencimento)->toBe(10)
        ->and($vigente->vigencia_inicio->toDateString())->toBe($this->clinica->data_inicio_atendimento->toDateString())
        ->and($vigente->vigencia_fim)->toBeNull();
});

it('reajuste fecha a vigência anterior sem sobrescrever o valor (RN-1201)', function () {
    Carbon::setTestNow('2026-11-03 10:00');
    $this->actingAs($this->admin);

    $this->clinica->update(['honorario_valor' => '1950.00']);

    $historico = $this->clinica->honorarios()->get();
    expect($historico)->toHaveCount(2)
        ->and($historico[0]->valor)->toBe('1950.00')
        ->and($historico[0]->vigencia_inicio->toDateString())->toBe('2026-11-03')
        ->and($historico[0]->vigencia_fim)->toBeNull()
        ->and($historico[0]->registrado_por_id)->toBe($this->admin->id)
        ->and($historico[1]->valor)->toBe('1800.00')
        ->and($historico[1]->vigencia_fim->toDateString())->toBe('2026-11-02');

    // corrigido de novo no mesmo dia: ajusta a vigência de hoje, sem abrir outra
    $this->clinica->update(['honorario_valor' => '1900.00', 'honorario_dia_vencimento' => 15]);
    expect($this->clinica->honorarios()->count())->toBe(2)
        ->and($this->clinica->honorarios()->first()->valor)->toBe('1900.00')
        ->and($this->clinica->honorarios()->first()->dia_vencimento)->toBe(15);

    Carbon::setTestNow();
});

it('não aceita honorário zerado nem dia de vencimento inválido (RN-1203)', function () {
    expect(fn () => $this->clinica->update(['honorario_valor' => '0']))->toThrow(RegraDeNegocioException::class)
        ->and(fn () => $this->clinica->update(['honorario_valor' => '100', 'honorario_dia_vencimento' => 31]))->toThrow(RegraDeNegocioException::class);
});

it('informa o honorário na aba Cobrança do cadastro da empresa', function () {
    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('empresa'));
    Filament::setTenant($this->clinica);

    Livewire::test(CadastroEmpresa::class)
        ->assertSee('Honorário mensal')->assertSee('Histórico de honorários')
        ->fillForm(['honorario_valor' => '2.100,00', 'honorario_dia_vencimento' => 5])
        ->call('salvar')
        ->assertHasNoFormErrors();

    expect($this->clinica->fresh()->honorario_valor)->toBe('2100.00')
        ->and($this->clinica->honorarios()->first()->valor)->toBe('2100.00');
});
