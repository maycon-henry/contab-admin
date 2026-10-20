<?php

use App\Actions\Competencias\FecharCompetencia;
use App\Actions\Competencias\ObterCompetencia;
use App\Actions\Itens\AceitarItem;
use App\Actions\Itens\EnviarDocumentosDoItem;
use App\Domain\Competencia as Mes;
use App\Enums\MotivoRecusa;
use App\Enums\ResultadoCertidao;
use App\Enums\SituacaoItem;
use App\Exceptions\RegraDeNegocioException;
use App\Filament\Escritorio\Resources\FilaAvaliacao\Pages\ListFilaAvaliacao;
use App\Filament\Portal\Resources\DocumentosDoMes\Pages\ListDocumentosDoMes;
use App\Models\Competencia;
use App\Models\Empresa;
use App\Models\ItemCompetencia;
use App\Models\User;
use App\Notifications\ItemRecusadoNotification;
use Database\Seeders\DesenvolvimentoSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DesenvolvimentoSeeder::class);
    $this->admin = User::where('email', 'admin@contab.test')->first();
    $this->bruno = User::where('email', 'colaborador@contab.test')->first();
    $this->carla = User::where('email', 'empresa@contab.test')->first();
    $this->demo = Empresa::withoutGlobalScopes()->where('cnpj', 'like', '22333444%')->first();
    setPermissionsTeamId($this->admin->escritorio_id);
});

it('gera a competência de forma idempotente (RN-620)', function () {
    $antes = ItemCompetencia::withoutGlobalScopes()->count();
    app(ObterCompetencia::class)->executar($this->demo, Mes::padrao());
    app(ObterCompetencia::class)->executar($this->demo, Mes::padrao());

    expect(ItemCompetencia::withoutGlobalScopes()->count())->toBe($antes);
});

it('não abre competência futura (RN-603)', function () {
    expect(fn () => app(ObterCompetencia::class)->executar($this->demo, Mes::atual()->seguinte()))->toThrow(RegraDeNegocioException::class);
});

it('aproveita certidão ainda válida (RN-656)', function () {
    $item = ItemCompetencia::withoutGlobalScopes()->where('empresa_id', $this->demo->id)->where('nome', 'like', 'CND Federal%')
        ->whereHas('competencia', fn ($q) => $q->withoutGlobalScopes()->whereDate('competencia', Mes::padrao()->paraBanco()))->first();

    expect($item->situacao)->toBe(SituacaoItem::Aceito)->and($item->aceito_por_validade)->toBeTrue();
});

it('não aceita documento vencido (RN-652)', function () {
    $item = ItemCompetencia::withoutGlobalScopes()->where('empresa_id', $this->demo->id)->where('nome', 'like', 'CRF%')
        ->where('situacao', SituacaoItem::EmAvaliacao)->first();

    expect(fn () => app(AceitarItem::class)->executar($item, $this->bruno, today()->subDay()->toDateString(), ResultadoCertidao::Negativa))
        ->toThrow(RegraDeNegocioException::class, 'vencido');
});

it('não fecha a competência com itens obrigatórios pendentes (RN-631)', function () {
    $competencia = Competencia::withoutGlobalScopes()->where('empresa_id', $this->demo->id)->whereDate('competencia', Mes::padrao()->paraBanco())->first();

    expect(fn () => app(FecharCompetencia::class)->executar($competencia, $this->admin))->toThrow(RegraDeNegocioException::class);
});

it('bloqueia envio em competência fechada (RN-644)', function () {
    $item = ItemCompetencia::withoutGlobalScopes()->where('empresa_id', $this->demo->id)
        ->whereHas('competencia', fn ($q) => $q->withoutGlobalScopes()->where('situacao', 'fechada'))->first();
    $item->update(['situacao' => SituacaoItem::Pendente]);

    expect(fn () => app(EnviarDocumentosDoItem::class)->executar($item, $this->carla, ['x.pdf' => 'x.pdf']))
        ->toThrow(RegraDeNegocioException::class, 'fechada');
});

it('empresa envia pelo portal e escritório recusa pela fila de avaliação', function () {
    Notification::fake();

    // portal: botão "Reenviar" no extrato recusado da Padaria (competência padrão)
    $padaria = Empresa::withoutGlobalScopes()->where('cnpj', 'like', '33444555%')->first();
    $this->actingAs($this->carla);
    Filament::setCurrentPanel(Filament::getPanel('portal'));
    Filament::setTenant($padaria);

    $item = ItemCompetencia::where('empresa_id', $padaria->id)->where('nome', 'Extrato bancário')->where('situacao', SituacaoItem::Recusado)
        ->whereHas('competencia', fn ($q) => $q->whereDate('competencia', Mes::padrao()->paraBanco()))->first();
    expect($item)->not->toBeNull();

    Livewire::test(ListDocumentosDoMes::class)
        ->callTableAction('enviar', $item, data: ['arquivos' => [UploadedFile::fake()->create('extrato.pdf', 20, 'application/pdf')]])
        ->assertHasNoTableActionErrors();

    expect($item->fresh()->situacao)->toBe(SituacaoItem::EmAvaliacao)
        ->and($item->documentos()->withoutGlobalScopes()->count())->toBeGreaterThan(0);

    // escritório: botão "Recusar" na fila
    $this->actingAs($this->bruno);
    Filament::setCurrentPanel(Filament::getPanel('escritorio'));

    Livewire::test(ListFilaAvaliacao::class)
        ->callTableAction('recusar', $item, data: ['motivo' => MotivoRecusa::Ilegivel->value, 'complemento' => 'Arquivo cortado'])
        ->assertHasNoTableActionErrors();

    expect($item->fresh()->situacao)->toBe(SituacaoItem::Recusado)
        ->and($item->avaliacoes()->count())->toBeGreaterThan(0);
    Notification::assertSentTo($this->carla, ItemRecusadoNotification::class);
});
