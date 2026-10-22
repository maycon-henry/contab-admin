<?php

use App\Actions\Admissao\AtualizarDocumentosDoFuncionario;
use App\Actions\Funcionarios\GerarDossieFuncionario;
use App\Enums\GrupoDocumentoFuncionario;
use App\Enums\SituacaoContrato;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Contrato;
use App\Models\Documento;
use App\Models\Empresa;
use App\Models\Funcionario;
use App\Models\User;
use Database\Seeders\DesenvolvimentoSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(DesenvolvimentoSeeder::class);
    $this->admin = User::where('email', 'admin@contab.test')->first();
    $this->bruno = User::where('email', 'colaborador@contab.test')->first();
    $this->eduardo = User::where('email', 'eduardo@contab.test')->first(); // financeiro e despesas: não consulta funcionários
    $this->carla = User::where('email', 'empresa@contab.test')->first();
    $this->demo = Empresa::withoutGlobalScopes()->where('cnpj', 'like', '22333444%')->first();
    $this->admitido = Contrato::withoutGlobalScopes()->where('empresa_id', $this->demo->id)->where('situacao', SituacaoContrato::Admitido)->first();
    $this->funcionario = Funcionario::withoutGlobalScopes()->find($this->admitido->funcionario_id);
    setPermissionsTeamId($this->admin->escritorio_id);
});

it('liga o documento ao funcionário e guarda os dados do arquivo (RN-568)', function () {
    Storage::fake('local');
    Storage::disk('local')->put('escritorios/1/admissoes/comprovante-novo.pdf', '%PDF-1.4 comprovante');

    app(AtualizarDocumentosDoFuncionario::class)->executar($this->admitido, $this->carla,
        ['comprovante_residencia' => 'escritorios/1/admissoes/comprovante-novo.pdf'], ['comprovante_residencia' => 'comprovante-novo.pdf']);

    $novo = $this->admitido->documentos()->where('tipo', 'comprovante_residencia')->first();
    expect($novo->funcionario_id)->toBe($this->funcionario->id)
        ->and($novo->hash)->toBe(hash('sha256', '%PDF-1.4 comprovante'))
        ->and($novo->tamanho)->toBe(strlen('%PDF-1.4 comprovante'))
        ->and($novo->grupo())->toBe(GrupoDocumentoFuncionario::Pessoais);
});

it('classifica os documentos em grupos (RN-569)', function () {
    expect(GrupoDocumentoFuncionario::doTipo('documento_com_foto'))->toBe(GrupoDocumentoFuncionario::Pessoais)
        ->and(GrupoDocumentoFuncionario::doTipo('documentos_dependentes'))->toBe(GrupoDocumentoFuncionario::Dependentes)
        ->and(GrupoDocumentoFuncionario::doTipo('aso_admissional'))->toBe(GrupoDocumentoFuncionario::Admissao)
        ->and(GrupoDocumentoFuncionario::doTipo('desligamento_trct'))->toBe(GrupoDocumentoFuncionario::Desligamento)
        ->and(GrupoDocumentoFuncionario::rotuloDoTipo('desligamento_trct'))->toBe('TRCT assinado')
        ->and(GrupoDocumentoFuncionario::doTipo('outro'))->toBe(GrupoDocumentoFuncionario::Outros);
});

it('mostra os documentos do funcionário no painel da empresa, só para quem consulta funcionários (RN-569)', function () {
    $url = "/admin/empresa/{$this->demo->id}/funcionarios/{$this->funcionario->id}/documentos";

    $this->actingAs($this->bruno)->get($url)->assertOk()
        ->assertSee('Documentos — '.$this->funcionario->nome)
        ->assertSee('Documento de identificação com foto')
        ->assertSee('Baixar dossiê (.zip)');

    expect($this->actingAs($this->eduardo)->get($url)->status())->toBe(403);

    // RN-100 — outro escritório não abre
    $outro = User::where('email', 'admin2@contab.test')->first();
    $r = $this->actingAs($outro)->get($url);
    expect($r->status())->toBeIn([302, 403, 404])->and($r->getContent())->not->toContain($this->funcionario->nome);
});

it('mostra os documentos no portal só para a empresa do funcionário (RN-240, RN-569)', function () {
    $this->actingAs($this->carla)->get("/portal/{$this->demo->id}/funcionarios/{$this->funcionario->id}/documentos")
        ->assertOk()->assertSee('ASO admissional');

    // funcionário de outra empresa do mesmo escritório, que a Carla não acessa
    $alheio = Funcionario::withoutGlobalScopes()->where('escritorio_id', $this->demo->escritorio_id)
        ->whereNotIn('empresa_id', $this->carla->empresasIds())->first();
    expect($this->actingAs($this->carla)->get("/portal/{$this->demo->id}/funcionarios/{$alheio->id}/documentos")->status())->toBe(404);
});

it('gera o dossiê em pastas, com as versões anteriores à parte (RN-573)', function () {
    Storage::disk('local')->put('escritorios/1/admissoes/comprovante-v2.pdf', '%PDF-1.4 v2');
    app(AtualizarDocumentosDoFuncionario::class)->executar($this->admitido, $this->carla,
        ['comprovante_residencia' => 'escritorios/1/admissoes/comprovante-v2.pdf'], ['comprovante_residencia' => 'comprovante-v2.pdf']);

    $zip = app(GerarDossieFuncionario::class)->executar($this->funcionario, $this->bruno);
    $arquivos = lerZip($zip['caminho']);
    $data = ($this->admitido->data_admissao ?? $this->admitido->data_admissao_prevista)->format('d-m-Y');

    Storage::disk('local')->delete('escritorios/1/admissoes/comprovante-v2.pdf');

    expect(collect($arquivos)->contains(fn ($a) => str_ends_with($a, '/01 Pessoais/Comprovante de residência.pdf')))->toBeTrue()
        ->and(collect($arquivos)->contains(fn ($a) => str_ends_with($a, "/03 Admissão {$data}/ASO admissional.pdf")))->toBeTrue()
        ->and(collect($arquivos)->contains(fn ($a) => str_contains($a, '/Versões anteriores/01 Pessoais/Comprovante de residência - enviado em')))->toBeTrue()
        ->and($zip['nome'])->toStartWith('dossie-');
});

it('no dossiê da empresa só vão os documentos visíveis para ela (RN-904)', function () {
    Documento::withoutGlobalScopes()->where('funcionario_id', $this->funcionario->id)->where('tipo', 'aso_admissional')
        ->update(['visivel_empresa' => false]);

    $daEmpresa = lerZip(app(GerarDossieFuncionario::class)->executar($this->funcionario, $this->carla)['caminho']);
    $doEscritorio = lerZip(app(GerarDossieFuncionario::class)->executar($this->funcionario, $this->bruno)['caminho']);

    expect(collect($daEmpresa)->contains(fn ($a) => str_contains($a, 'ASO admissional')))->toBeFalse()
        ->and(collect($doEscritorio)->contains(fn ($a) => str_contains($a, 'ASO admissional')))->toBeTrue();
});

it('não gera dossiê para quem não pode ver o funcionário', function () {
    $antonio = User::where('email', 'antonio@padaria.test')->first(); // só a Padaria

    expect(fn () => app(GerarDossieFuncionario::class)->executar($this->funcionario, $antonio))->toThrow(RegraDeNegocioException::class)
        ->and(fn () => app(GerarDossieFuncionario::class)->executar($this->funcionario, $this->eduardo))->toThrow(RegraDeNegocioException::class);
});

/** @return list<string> */
function lerZip(string $caminho): array
{
    $zip = new ZipArchive;
    $zip->open($caminho);
    $nomes = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $nomes[] = $zip->getNameIndex($i);
    }
    $zip->close();
    @unlink($caminho);

    return $nomes;
}
