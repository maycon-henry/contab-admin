<?php

use App\Actions\Competencias\FecharCompetencia;
use App\Actions\Competencias\ObterCompetencia;
use App\Actions\Itens\AceitarItem;
use App\Actions\Itens\EnviarDocumentosDoItem;
use App\Actions\Usuarios\DefinirAtribuicoes;
use App\Domain\Competencia as Mes;
use App\Enums\Atribuicao;
use App\Enums\MotivoRecusa;
use App\Enums\ResultadoCertidao;
use App\Enums\SituacaoItem;
use App\Exceptions\RegraDeNegocioException;
use App\Filament\Empresa\Resources\Documentos\Pages\ListDocumentos;
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

it('documento enviado pelo escritório já fica aceito (RN-646)', function () {
    $item = ItemCompetencia::withoutGlobalScopes()->where('empresa_id', $this->demo->id)->where('tipo', 'documento')
        ->where('exige_validade', false)->where('eh_certidao', false)
        ->whereHas('competencia', fn ($q) => $q->withoutGlobalScopes()->where('situacao', 'aberta'))->first();
    $item->update(['situacao' => SituacaoItem::Pendente]);

    app(EnviarDocumentosDoItem::class)->executar($item, $this->bruno, ['x.pdf' => 'x.pdf']);

    $item->refresh();
    expect($item->situacao)->toBe(SituacaoItem::Aceito)
        ->and($item->avaliado_por_id)->toBe($this->bruno->id)
        ->and($item->avaliacoes()->latest('id')->first()->resultado)->toBe(SituacaoItem::Aceito->value)
        ->and($item->documentos()->withoutGlobalScopes()->latest('id')->first()->origem)->toBe('escritorio');
});

it('escritório não envia certidão vencida em nome da empresa (RN-646, RN-652)', function () {
    $item = ItemCompetencia::withoutGlobalScopes()->where('empresa_id', $this->demo->id)->where('exige_validade', true)
        ->whereHas('competencia', fn ($q) => $q->withoutGlobalScopes()->where('situacao', 'aberta'))->first();
    $item->update(['situacao' => SituacaoItem::Pendente]);
    $documentosAntes = $item->documentos()->withoutGlobalScopes()->count();

    expect(fn () => app(EnviarDocumentosDoItem::class)->executar($item, $this->bruno, ['x.pdf' => 'x.pdf'], today()->subDay()->toDateString(), ResultadoCertidao::Negativa))
        ->toThrow(RegraDeNegocioException::class, 'vencido');
    expect($item->fresh()->situacao)->toBe(SituacaoItem::Pendente)
        ->and($item->documentos()->withoutGlobalScopes()->count())->toBe($documentosAntes);
});

it('colaborador sem permissão de avaliar não envia em nome da empresa (RN-646)', function () {
    $daniela = User::where('email', 'daniela@contab.test')->first();
    app(DefinirAtribuicoes::class)->executar($daniela, [Atribuicao::Cobranca, Atribuicao::Despesas]);
    $item = ItemCompetencia::withoutGlobalScopes()->where('empresa_id', $this->demo->id)->where('tipo', 'documento')
        ->whereHas('competencia', fn ($q) => $q->withoutGlobalScopes()->where('situacao', 'aberta'))->first();
    $item->update(['situacao' => SituacaoItem::Pendente]);

    expect(fn () => app(EnviarDocumentosDoItem::class)->executar($item, $daniela->fresh(), ['x.pdf' => 'x.pdf']))
        ->toThrow(RegraDeNegocioException::class, 'permissão');
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

it('escritório envia pelo menu Documentos da empresa e o documento já fica aceito (RN-646)', function () {
    $item = ItemCompetencia::withoutGlobalScopes()->where('empresa_id', $this->demo->id)->where('nome', 'Extrato bancário')
        ->whereIn('situacao', SituacaoItem::cobraveis())
        ->whereHas('competencia', fn ($q) => $q->withoutGlobalScopes()->where('situacao', 'aberta'))->first();
    expect($item)->not->toBeNull();

    $this->actingAs($this->bruno);
    $this->get("/admin/empresa/{$this->demo->id}/documentos")->assertOk()->assertSee('Documentos');

    Filament::setCurrentPanel(Filament::getPanel('empresa'));
    Filament::setTenant($this->demo);

    Livewire::test(ListDocumentos::class)
        ->filterTable('competencia', $item->competencia->competencia->toDateString())
        ->assertCanSeeTableRecords([$item])
        ->callTableAction('enviar', $item, data: ['arquivos' => [UploadedFile::fake()->create('extrato.pdf', 20, 'application/pdf')]])
        ->assertHasNoTableActionErrors();

    expect($item->fresh()->situacao)->toBe(SituacaoItem::Aceito)
        ->and($item->fresh()->avaliado_por_id)->toBe($this->bruno->id);
});

it('menu Documentos da empresa exige a atribuição de avaliar documentos', function () {
    $eduardo = User::where('email', 'eduardo@contab.test')->first(); // financeiro e despesas
    $eduardo->forceFill(['convite_aceito_em' => now(), 'password' => bcrypt('x')])->save();

    $this->actingAs($eduardo)->get("/admin/empresa/{$this->demo->id}/documentos")->assertForbidden();
});
