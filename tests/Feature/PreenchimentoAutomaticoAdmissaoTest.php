<?php

use App\Actions\Admissao\AvaliarDocumentoAdmissao;
use App\Actions\Admissao\PreencherComDocumento;
use App\Actions\Admissao\SalvarAdmissao;
use App\Contracts\LeitorDadosDeDocumento;
use App\Domain\Admissao\PreenchimentoAutomatico;
use App\Domain\Admissao\ResumoAdmissao;
use App\Enums\DocumentoAdmissao;
use App\Enums\SituacaoContrato;
use App\Exceptions\RegraDeNegocioException;
use App\Filament\Portal\Resources\Admissoes\Pages\CreateAdmissao;
use App\Models\Contrato;
use App\Models\Empresa;
use App\Models\User;
use Database\Seeders\DesenvolvimentoSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/** Leitor falso: devolve o que o teste mandar, sem chamar a API. */
function leitorFalso(array $resposta, bool $disponivel = true, ?Throwable $erro = null): LeitorDadosDeDocumento
{
    $leitor = new class($resposta, $disponivel, $erro) implements LeitorDadosDeDocumento
    {
        public array $chamadas = [];

        public function __construct(private array $resposta, private bool $ativo, private ?Throwable $erro) {}

        public function disponivel(): bool
        {
            return $this->ativo;
        }

        public function ler(string $conteudo, string $mimeType, string $tipoDocumento, array $campos): array
        {
            $this->chamadas[] = compact('mimeType', 'tipoDocumento', 'campos');
            if ($this->erro) {
                throw $this->erro;
            }

            return $this->resposta;
        }
    };
    app()->instance(LeitorDadosDeDocumento::class, $leitor);

    return $leitor;
}

beforeEach(function () {
    $this->seed(DesenvolvimentoSeeder::class);
    $this->bruno = User::where('email', 'colaborador@contab.test')->first();
    $this->carla = User::where('email', 'empresa@contab.test')->first();
    $this->demo = Empresa::withoutGlobalScopes()->where('cnpj', 'like', '22333444%')->first();
    setPermissionsTeamId($this->bruno->escritorio_id);
});

// regras.md RN-579
it('preenche só os campos vazios, sem mudar o que já foi digitado', function () {
    $dominio = new PreenchimentoAutomatico;

    $lidos = $dominio->normalizar(['nome' => 'MARIA DA SILVA SANTOS', 'cpf' => '529.982.247-25', 'data_nascimento' => '15/03/1990', 'sexo' => 'Feminino']);
    $preencher = $dominio->camposAPreencher(['nome' => 'Maria S. Santos', 'cpf' => ''], $lidos);

    expect($lidos['nome'])->toBe('Maria da Silva Santos')
        ->and($preencher)->toBe(['cpf' => '52998224725', 'data_nascimento' => '1990-03-15', 'sexo' => 'F'])
        ->and($preencher)->not->toHaveKey('nome');
});

// regras.md RN-579
it('descarta valores inválidos lidos do documento', function () {
    $lidos = (new PreenchimentoAutomatico)->normalizar([
        'cpf' => '111.111.111-11', 'data_nascimento' => '2999-01-01', 'uf' => 'XX', 'estado_civil' => 'enrolado', 'cep' => '123', 'municipio' => null,
    ]);

    expect($lidos)->toBe([]);
});

// regras.md RN-579 — o comprovante de residência pode estar em nome de outra pessoa
it('do comprovante de residência só aproveita o endereço', function () {
    $leitor = leitorFalso(['nome' => 'João Locador', 'cpf' => '52998224725', 'logradouro' => 'RUA DAS FLORES', 'numero' => '10', 'uf' => 'sp', 'cep' => '01001-000']);

    $campos = app(PreencherComDocumento::class)->executar($this->carla, $this->demo, DocumentoAdmissao::ComprovanteResidencia, 'pdf', 'application/pdf', ['numero' => '99']);

    expect($campos)->toBe(['logradouro' => 'Rua das Flores', 'uf' => 'SP', 'cep' => '01001000'])
        ->and($leitor->chamadas[0]['campos'])->not->toHaveKey('nome');
});

// regras.md RN-579
it('não chama a leitura para documentos sem dados do cadastro nem quando está desligada', function () {
    $leitor = leitorFalso(['nome' => 'X']);
    expect(app(PreencherComDocumento::class)->executar($this->carla, $this->demo, DocumentoAdmissao::AsoAdmissional, 'pdf', 'application/pdf', []))->toBe([])
        ->and($leitor->chamadas)->toBe([]);

    $desligado = leitorFalso(['nome' => 'X'], disponivel: false);
    expect(app(PreencherComDocumento::class)->executar($this->carla, $this->demo, DocumentoAdmissao::DocumentoComFoto, 'pdf', 'application/pdf', []))->toBe([])
        ->and($desligado->chamadas)->toBe([]);
});

// regras.md RN-579 / RN-240
it('não deixa usuário de outra empresa usar a leitura e avisa quando a leitura falha', function () {
    leitorFalso([], erro: new RuntimeException('timeout'));
    $outra = $this->demo->replicate();
    $outra->forceFill(['cnpj' => '11444777000161', 'razao_social' => 'Outra Empresa Ltda'])->save();

    expect(fn () => app(PreencherComDocumento::class)->executar($this->carla, $outra, DocumentoAdmissao::DocumentoComFoto, 'x', 'image/png', []))
        ->toThrow(RegraDeNegocioException::class, 'não vinculada');

    expect(fn () => app(PreencherComDocumento::class)->executar($this->carla, $this->demo, DocumentoAdmissao::DocumentoComFoto, 'x', 'image/png', []))
        ->toThrow(RegraDeNegocioException::class, 'Preencha os dados manualmente');
});

// regras.md RN-579 e RN-586 — tela do portal
it('no portal, avisa da facilidade, preenche ao anexar e mostra o resumo com pendências', function () {
    Storage::fake('local');
    leitorFalso(['nome' => 'MARIA DA SILVA', 'cpf' => '52998224725', 'data_nascimento' => '1990-03-15', 'sexo' => 'F', 'rg' => '123456']);
    $this->actingAs($this->carla);
    Filament::setCurrentPanel(Filament::getPanel('portal'));
    Filament::setTenant($this->demo);

    Livewire::test(CreateAdmissao::class)
        ->assertSet('defaultAction', 'avisoPreenchimentoAutomatico')
        ->fillForm(['funcionario.nome' => 'Maria Silva'])
        ->set('data.documentos.documento_com_foto', [UploadedFile::fake()->image('cnh.jpg')])
        ->assertSet('data.funcionario.nome', 'Maria Silva') // digitado antes: não muda
        ->assertSet('data.funcionario.cpf', '529.982.247-25')
        ->assertSet('data.funcionario.data_nascimento', '1990-03-15')
        ->assertSet('data.funcionario.rg', '123456')
        ->assertSee('Preenchimento automático')
        ->assertSee('Falta preencher antes de enviar')
        ->assertSee('Contrato: Cargo')
        ->assertActionDisabled(TestAction::make('salvarEEnviar')->schemaComponent('resumo.envio', schema: 'form'));
});

// regras.md RN-550 — quem salvou a última versão do formulário
it('guarda quem salvou a última versão do formulário', function () {
    $rascunho = Contrato::withoutGlobalScopes()->where('empresa_id', $this->demo->id)->where('situacao', SituacaoContrato::Rascunho)->first();

    app(SalvarAdmissao::class)->atualizar($rascunho, $this->carla, ['funcionario' => ['telefone' => '(11) 90000-0000']]);
    expect($rascunho->fresh()->formulario_salvo_por_id)->toBe($this->carla->id);

    app(SalvarAdmissao::class)->atualizar($rascunho->fresh(), $this->bruno, ['funcionario' => ['telefone' => '(11) 90000-0001']]);
    expect($rascunho->fresh())->formulario_salvo_por_id->toBe($this->bruno->id)
        ->formulario_salvo_em->not->toBeNull();
});

// regras.md RN-593 — quem do escritório aceitou o documento
it('guarda quem do escritório avaliou cada documento', function () {
    $contrato = Contrato::withoutGlobalScopes()->where('situacao', SituacaoContrato::EmAnalise)->first();
    $documento = $contrato->documentos()->first();

    app(AvaliarDocumentoAdmissao::class)->executar($documento, $this->bruno, true);

    expect($documento->fresh())->avaliacao->toBe('aceito')
        ->avaliado_por_id->toBe($this->bruno->id)
        ->avaliado_em->not->toBeNull();
});

// regras.md RN-586 — resumo usa os mesmos obrigatórios da solicitação
it('lista as pendências do resumo a partir do formulário', function () {
    $resumo = app(ResumoAdmissao::class)->montar([
        'funcionario' => ['nome' => 'Ana'],
        'tipo_contrato' => 'experiencia',
        'documentos' => ['documento_com_foto' => ['arquivo.pdf']],
    ]);

    expect($resumo['pendencias'])->toContain('Dados pessoais: CPF', 'Contrato: Cargo', 'Contrato: duração do 1º período de experiência', 'Documento: Comprovante de residência')
        ->not->toContain('Dados pessoais: Nome completo', 'Documento: Documento de identificação com foto');
});
