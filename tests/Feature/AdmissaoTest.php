<?php

use App\Actions\Admissao\AssumirAdmissao;
use App\Actions\Admissao\AtualizarDocumentosDoFuncionario;
use App\Actions\Admissao\AvaliarDocumentoAdmissao;
use App\Actions\Admissao\ConcluirAdmissao;
use App\Actions\Admissao\DevolverAdmissao;
use App\Actions\Admissao\SalvarAdmissao;
use App\Actions\Admissao\SolicitarAdmissao;
use App\Actions\Admissao\TransferirResponsavelAdmissao;
use App\Domain\Consultas\PendenciasDaEmpresa;
use App\Enums\SituacaoContrato;
use App\Exceptions\RegraDeNegocioException;
use App\Filament\Escritorio\Resources\Admissoes\Pages\ListAdmissoes;
use App\Filament\Escritorio\Resources\Admissoes\Pages\ViewAdmissao;
use App\Filament\Portal\Resources\Admissoes\Pages\AtualizarDocumentos;
use App\Filament\Portal\Resources\Admissoes\Pages\EditAdmissao;
use App\Models\Contrato;
use App\Models\Empresa;
use App\Models\User;
use App\Notifications\AdmissaoSolicitadaNotification;
use Database\Seeders\DesenvolvimentoSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DesenvolvimentoSeeder::class);
    $this->bruno = User::where('email', 'colaborador@contab.test')->first();
    $this->daniela = User::where('email', 'daniela@contab.test')->first();
    $this->carla = User::where('email', 'empresa@contab.test')->first();
    setPermissionsTeamId($this->bruno->escritorio_id);
});

it('não deixa solicitar com pendências (RN-586)', function () {
    $rascunho = Contrato::withoutGlobalScopes()->where('situacao', SituacaoContrato::Rascunho)->first();

    expect(fn () => app(SolicitarAdmissao::class)->executar($rascunho, $this->carla))
        ->toThrow(RegraDeNegocioException::class, 'pendências');
});

it('impede a empresa de alterar depois de solicitar (RN-588)', function () {
    $emAnalise = Contrato::withoutGlobalScopes()->where('situacao', SituacaoContrato::EmAnalise)->whereNull('responsavel_id')->first();
    $this->actingAs($this->carla);

    expect(fn () => app(SalvarAdmissao::class)->atualizar($emAnalise, $this->carla, ['cargo' => 'Outro']))
        ->toThrow(RegraDeNegocioException::class, 'RN-588');
});

it('grava quem iniciou e quem admitiu, sem permitir sobrescrever (RN-550, RN-551)', function () {
    $contrato = Contrato::withoutGlobalScopes()->where('situacao', SituacaoContrato::EmAnalise)->whereNull('responsavel_id')->first();

    app(AssumirAdmissao::class)->executar($contrato, $this->bruno);
    app(TransferirResponsavelAdmissao::class)->executar($contrato->fresh(), $this->bruno, $this->daniela);

    $contrato->refresh();
    expect($contrato->atendimento_iniciado_por_id)->toBe($this->bruno->id)
        ->and($contrato->responsavel_id)->toBe($this->daniela->id);

    foreach ($contrato->documentos as $doc) {
        $doc->update(['avaliacao' => 'aceito']);
    }

    app(ConcluirAdmissao::class)->executar($contrato->fresh(), $this->daniela, [
        'matricula' => '900', 'data_admissao' => $contrato->data_admissao_prevista->toDateString(), 'categoria' => '101',
        'cbo' => '411005', 'natureza_atividade' => 'urbana', 'regime_jornada' => 'controle_horario',
    ]);

    $contrato->refresh();
    expect($contrato->situacao)->toBe(SituacaoContrato::Admitido)
        ->and($contrato->atendimento_iniciado_por_id)->toBe($this->bruno->id)
        ->and($contrato->admitido_por_id)->toBe($this->daniela->id);

    // nova conclusão é recusada
    expect(fn () => app(ConcluirAdmissao::class)->executar($contrato, $this->bruno, []))->toThrow(RegraDeNegocioException::class);
});

it('exige documentos aceitos para concluir (RN-598)', function () {
    $contrato = Contrato::withoutGlobalScopes()->where('situacao', SituacaoContrato::EmAnalise)->whereNull('responsavel_id')->first();
    app(AssumirAdmissao::class)->executar($contrato, $this->bruno);

    expect(fn () => app(ConcluirAdmissao::class)->executar($contrato->fresh(), $this->bruno, [
        'matricula' => '1', 'data_admissao' => '2026-11-01', 'categoria' => '101', 'cbo' => '411005',
        'natureza_atividade' => 'urbana', 'regime_jornada' => 'controle_horario',
    ]))->toThrow(RegraDeNegocioException::class, 'pendências');
});

it('assume pela tela da fila e solicita pela tela do portal', function () {
    Notification::fake();

    // escritório: botão "Assumir" na fila
    $this->actingAs($this->bruno);
    Filament::setCurrentPanel(Filament::getPanel('escritorio'));
    $contrato = Contrato::where('situacao', SituacaoContrato::EmAnalise)->whereNull('responsavel_id')->first();

    Livewire::test(ListAdmissoes::class)->callTableAction('assumir', $contrato);
    expect($contrato->fresh()->responsavel_id)->toBe($this->bruno->id);

    Livewire::test(ViewAdmissao::class, ['record' => $contrato->id])->assertOk()->assertSee($contrato->funcionario->nome);

    // portal: devolvida → corrigir → aba "Resumo e envio" → "Salvar e enviar" (RN-586)
    $this->actingAs($this->carla);
    Filament::setCurrentPanel(Filament::getPanel('portal'));
    $devolvida = Contrato::where('situacao', SituacaoContrato::Devolvida)->first();
    Filament::setTenant($devolvida->empresa);

    $enviar = TestAction::make('enviarParaAnalise')->schemaComponent('resumo.envio', schema: 'form');
    Livewire::test(EditAdmissao::class, ['record' => $devolvida->id])
        ->assertSee('Tudo pronto para enviar')
        ->assertActionEnabled($enviar)
        ->callAction($enviar);

    $devolvida->refresh();
    expect($devolvida->situacao)->toBe(SituacaoContrato::EmAnalise)
        ->and($devolvida->responsavel_id)->not->toBeNull(); // volta para o mesmo responsável (RN-595)
    Notification::assertSentTo($devolvida->responsavel, AdmissaoSolicitadaNotification::class);
});

it('edita só o cadastro pessoal do funcionário admitido (RN-525)', function () {
    $admitido = Contrato::withoutGlobalScopes()->where('situacao', SituacaoContrato::Admitido)->first();
    $salario = $admitido->salario;

    app(SalvarAdmissao::class)->atualizar($admitido, $this->bruno, [
        'funcionario' => ['telefone' => '(11) 91111-2222', 'logradouro' => 'Rua Nova'], 'salario' => 99999, 'cargo' => 'Diretor',
    ]);

    $admitido->refresh();
    expect($admitido->funcionario->telefone)->toBe('(11) 91111-2222')
        ->and($admitido->salario)->toEqual($salario) // contrato não é sobrescrito
        ->and($admitido->cargo)->not->toBe('Diretor')
        ->and($admitido->historicos()->where('acao', 'cadastro_atualizado')->exists())->toBeTrue();

    // a empresa continua sem poder alterar (RN-588)
    expect(fn () => app(SalvarAdmissao::class)->atualizar($admitido, $this->carla, ['funcionario' => ['telefone' => 'x']]))
        ->toThrow(RegraDeNegocioException::class);
});

it('mostra voltar e editar na ficha e o desligamento dentro da edição', function () {
    $admitido = Contrato::withoutGlobalScopes()->where('situacao', SituacaoContrato::Admitido)->first();
    $this->actingAs($this->bruno);
    Filament::setCurrentPanel(Filament::getPanel('escritorio'));

    $voltar = url("/admin/empresa/{$admitido->empresa_id}/funcionarios");

    Livewire::withQueryParams(['voltar' => $voltar])->test(ViewAdmissao::class, ['record' => $admitido->id])
        ->assertActionVisible('voltar')->assertActionHasUrl('voltar', $voltar)
        ->assertActionVisible('edit')
        ->assertActionDoesNotExist('iniciarDesligamento');

    Livewire::test(App\Filament\Escritorio\Resources\Admissoes\Pages\EditAdmissao::class, ['record' => $admitido->id])
        ->assertActionVisible('iniciarDesligamento')
        ->assertSee('Funcionário admitido')
        ->assertSee('Linha do tempo')
        ->assertSee('Funcionário admitido por'); // aba da linha do tempo (RN-575)

    expect((new App\Filament\Escritorio\Resources\Admissoes\Pages\EditAdmissao)->getRelationManagers())->toBe([]);
});

it('no portal, admite pela tela de Funcionários e o menu não tem Admissões', function () {
    $demo = Empresa::withoutGlobalScopes()->where('cnpj', 'like', '22333444%')->first();
    $admitido = Contrato::withoutGlobalScopes()->where('empresa_id', $demo->id)->where('situacao', SituacaoContrato::Admitido)->first();
    $rascunho = Contrato::withoutGlobalScopes()->where('empresa_id', $demo->id)->where('situacao', SituacaoContrato::Rascunho)->first();
    $this->actingAs($this->carla);

    $lista = $this->get("/portal/{$demo->id}/funcionarios")->assertOk()
        ->assertSee('Admitir novo')->assertDontSee('Admissões');
    // o antigo endereço da lista de admissões leva para Funcionários
    $this->get("/portal/{$demo->id}/admissoes")->assertRedirect(url("/portal/{$demo->id}/funcionarios"));

    // funcionário admitido: só a troca de documentos; rascunho: formulário completo
    expect($this->get("/portal/{$demo->id}/admissoes/{$admitido->id}/documentos")->status())->toBe(200)
        ->and($this->get("/portal/{$demo->id}/admissoes/{$admitido->id}")->status())->toBe(200)
        ->and($this->get("/portal/{$demo->id}/admissoes/{$admitido->id}/edit")->status())->toBe(403)
        ->and($this->get("/portal/{$demo->id}/admissoes/{$rascunho->id}/documentos")->status())->toBe(403);
});

it('documento trocado pela empresa fica em análise até o Operacional aceitar (RN-565 a RN-567)', function () {
    Storage::fake('local');
    $demo = Empresa::withoutGlobalScopes()->where('cnpj', 'like', '22333444%')->first();
    $admitido = Contrato::withoutGlobalScopes()->where('empresa_id', $demo->id)->where('situacao', SituacaoContrato::Admitido)->first();
    $antes = $admitido->documentos()->where('tipo', 'comprovante_residencia')->first();
    $telefone = $admitido->funcionario->telefone;
    $quantidade = $admitido->documentos()->count();

    $alterados = app(AtualizarDocumentosDoFuncionario::class)->executar($admitido, $this->carla,
        ['comprovante_residencia' => 'escritorios/1/admissoes/novo-comprovante.pdf'], ['comprovante_residencia' => 'novo-comprovante.pdf']);

    expect($alterados)->toHaveCount(1);
    $novo = $admitido->documentos()->where('tipo', 'comprovante_residencia')->first();
    expect($novo->id)->not->toBe($antes->id)
        ->and($novo->avaliacao)->toBeNull()
        ->and($antes->fresh()->substituido)->toBeTrue() // versão anterior preservada (RN-562)
        ->and($admitido->documentos()->count())->toBe($quantidade) // nenhum documento some ao enviar só um
        ->and($admitido->funcionario->fresh()->telefone)->toBe($telefone)
        ->and(PendenciasDaEmpresa::para($demo)->documentosDeFuncionariosParaAvaliar()->pluck('id')->all())->toBe([$novo->id]);

    // o Operacional avalia
    app(AvaliarDocumentoAdmissao::class)->executar($novo, $this->bruno, true);
    expect($novo->fresh()->avaliacao)->toBe('aceito')
        ->and(PendenciasDaEmpresa::para($demo)->documentosDeFuncionariosParaAvaliar()->count())->toBe(0)
        ->and($admitido->historicos()->where('acao', 'documentos_atualizados')->exists())->toBeTrue();

    // documento já avaliado não é avaliado de novo
    expect(fn () => app(AvaliarDocumentoAdmissao::class)->executar($novo->fresh(), $this->bruno, true))
        ->toThrow(RegraDeNegocioException::class);
});

it('troca documento pela tela "Atualizar documentos" do portal', function () {
    Storage::fake('local');
    $demo = Empresa::withoutGlobalScopes()->where('cnpj', 'like', '22333444%')->first();
    $admitido = Contrato::withoutGlobalScopes()->where('empresa_id', $demo->id)->where('situacao', SituacaoContrato::Admitido)->first();
    $quantidade = $admitido->documentos()->count();
    $this->actingAs($this->carla);
    Filament::setCurrentPanel(Filament::getPanel('portal'));
    Filament::setTenant($demo);

    Livewire::test(AtualizarDocumentos::class, ['record' => $admitido->id])
        ->assertOk()->assertSee('Como funciona')
        ->set('data.documentos.comprovante_residencia', [UploadedFile::fake()->create('conta-de-luz.pdf', 20, 'application/pdf')])
        ->call('save')->assertHasNoFormErrors();

    $novo = $admitido->documentos()->where('tipo', 'comprovante_residencia')->first();
    expect($novo->nome_original)->toBe('conta-de-luz.pdf')
        ->and($novo->avaliacao)->toBeNull()
        ->and($admitido->documentos()->count())->toBe($quantidade);
});

it('devolve à empresa marcando os documentos com problema e o motivo de cada um (RN-593, RN-595)', function () {
    Notification::fake();
    $contrato = Contrato::withoutGlobalScopes()->where('situacao', SituacaoContrato::EmAnalise)->whereNotNull('responsavel_id')->first();
    $comprovante = $contrato->documentos()->withoutGlobalScopes()->where('substituido', false)->where('tipo', 'comprovante_residencia')->first()
        ?? $contrato->documentos()->withoutGlobalScopes()->where('substituido', false)->first();
    $responsavel = User::find($contrato->responsavel_id);

    $this->actingAs($responsavel);
    Filament::setCurrentPanel(Filament::getPanel('escritorio'));

    Livewire::test(ViewAdmissao::class, ['record' => $contrato->id])
        ->callAction('devolver', [
            'documentos' => [$comprovante->id => ['recusar' => true, 'motivo' => 'vencido', 'complemento' => 'Comprovante com mais de 90 dias']],
            'motivo' => '',
        ])
        ->assertHasNoActionErrors();

    $comprovante->refresh();
    $contrato->refresh();
    expect($comprovante->avaliacao)->toBe('recusado')
        ->and($comprovante->motivo_recusa)->toBe('Documento vencido — Comprovante com mais de 90 dias')
        ->and($contrato->situacao)->toBe(SituacaoContrato::Devolvida)
        ->and($contrato->motivo_devolucao)->toContain('Documentos recusados')->toContain('Comprovante com mais de 90 dias');
});

it('não devolve sem documento marcado nem motivo (RN-595)', function () {
    $contrato = Contrato::withoutGlobalScopes()->where('situacao', SituacaoContrato::EmAnalise)->whereNotNull('responsavel_id')->first();

    expect(fn () => app(DevolverAdmissao::class)->executar($contrato, $this->daniela, '', []))
        ->toThrow(RegraDeNegocioException::class);
});
