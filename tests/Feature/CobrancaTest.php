<?php

use App\Actions\Cobranca\EnviarCobrancaDocumentacao;
use App\Domain\Cobranca\SelecionadorEmpresasParaCobranca;
use App\Models\CobrancaDocumentacao;
use App\Models\Empresa;
use App\Notifications\CobrancaDocumentacaoNotification;
use Carbon\CarbonImmutable;
use Database\Seeders\DesenvolvimentoSeeder;
use Illuminate\Support\Facades\Notification;

/** regras.md seção 16.1 — AGENTS.md seção 3.6 */
beforeEach(function () {
    $this->seed(DesenvolvimentoSeeder::class);
    Notification::fake();
    $this->selecionador = app(SelecionadorEmpresasParaCobranca::class);
    $this->demo = Empresa::withoutGlobalScopes()->where('cnpj', 'like', '22333444%')->first();
    $this->hoje = CarbonImmutable::today();
});

it('cobra o mês anterior (RN-1310)', function () {
    expect($this->selecionador->competenciaReferencia(CarbonImmutable::parse('2026-10-15'))->paraTela())->toBe('09/2026');
});

it('não cobra antes da data de corte e respeita a data própria da empresa (RN-1311)', function () {
    $dia3 = $this->hoje->startOfMonth()->addDays(2);
    expect($this->selecionador->deveCobrar($this->demo, $dia3))->toBeFalse();

    $this->demo->update(['dia_corte_cobranca' => 28]);
    expect($this->selecionador->deveCobrar($this->demo->fresh(), $this->hoje->startOfMonth()->addDays(19)))->toBeFalse();
});

it('não envia em fim de semana (RN-1312, sugestão D18)', function () {
    expect($this->selecionador->ehDiaDeEnvio(CarbonImmutable::parse('2026-10-17')))->toBeFalse()
        ->and($this->selecionador->ehDiaDeEnvio(CarbonImmutable::parse('2026-10-19')))->toBeTrue();
});

it('respeita a suspensão da cobrança (RN-1317)', function () {
    $this->demo->update(['cobranca_suspensa_ate' => $this->hoje->addDays(5), 'cobranca_suspensa_motivo' => 'Negociação']);

    expect($this->selecionador->deveCobrar($this->demo->fresh(), $this->hoje))->toBeFalse();
});

it('não envia e-mail sem pendências (RN-1314)', function () {
    // nos dados de exemplo, a Empresa Demo já entregou ou está em avaliação em tudo do mês de referência
    expect(app(EnviarCobrancaDocumentacao::class)->executar($this->demo, $this->hoje))->toBe('sem_pendencias');
    Notification::assertNothingSent();
});

it('envia um único e-mail por dia, com os itens cobráveis (RN-1315, RN-1320)', function () {
    $construtora = Empresa::withoutGlobalScopes()->where('cnpj', 'like', '44555666%')->first();
    $enviar = app(EnviarCobrancaDocumentacao::class);

    expect($enviar->executar($construtora, $this->hoje))->toBe('enviada')
        ->and($enviar->executar($construtora, $this->hoje))->toBe('ja_enviada');

    Notification::assertSentTimes(CobrancaDocumentacaoNotification::class, 1);
    $cobranca = CobrancaDocumentacao::withoutGlobalScopes()->where('empresa_id', $construtora->id)->whereDate('data_referencia', $this->hoje)->get();
    expect($cobranca)->toHaveCount(1)
        ->and(collect($cobranca->first()->itens)->pluck('nome'))->toContain('Extrato bancário');
});

it('registra empresa sem destinatário em vez de enviar (RN-1318)', function () {
    $tech = Empresa::withoutGlobalScopes()->where('cnpj', 'like', '66777888%')->first();

    expect(app(EnviarCobrancaDocumentacao::class)->executar($tech, $this->hoje))->toBe('sem_destinatario');
    Notification::assertNothingSent();
});

it('roda pelo comando agendado com opção de simulação', function () {
    $this->artisan('cobranca:documentacao-diaria', ['--data' => '2026-10-17'])->expectsOutputToContain('não é dia útil')->assertSuccessful();
    $this->artisan('cobranca:documentacao-diaria', ['--simular' => true])->assertSuccessful();
    Notification::assertNothingSent();
});
