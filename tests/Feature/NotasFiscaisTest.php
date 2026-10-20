<?php

use App\Actions\Competencias\ObterCompetencia;
use App\Actions\NotasFiscais\ImportarLoteXml;
use App\Actions\NotasFiscais\MoverNotaCompetencia;
use App\Domain\Competencia as Mes;
use App\Enums\SituacaoItem;
use App\Enums\SituacaoNota;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Empresa;
use App\Models\ItemCompetencia;
use App\Models\NotaFiscal;
use App\Models\User;
use Database\Seeders\DesenvolvimentoSeeder;
use Database\Seeders\Suporte\GeradorXmlNfe;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(DesenvolvimentoSeeder::class);
    $this->carla = User::where('email', 'empresa@contab.test')->first();
    $this->admin = User::where('email', 'admin@contab.test')->first();
    $this->demo = Empresa::withoutGlobalScopes()->where('cnpj', 'like', '22333444%')->first();
    setPermissionsTeamId($this->admin->escritorio_id);
});

function arquivoXml(string $conteudo): string
{
    $caminho = 'tmp/xml/'.Str::random(10).'.xml';
    Storage::disk('local')->put($caminho, $conteudo);

    return $caminho;
}

it('importou os exemplos com duplicadas, recusadas e cancelamento', function () {
    expect(NotaFiscal::withoutGlobalScopes()->where('empresa_id', $this->demo->id)->count())->toBe(10)
        ->and(NotaFiscal::withoutGlobalScopes()->where('empresa_id', $this->demo->id)->where('situacao', SituacaoNota::Cancelada)->count())->toBe(1);
});

it('coloca cada nota na sua competência e marca o item como em avaliação (RN-702, RN-717)', function () {
    $mes = Mes::atual();
    $competencia = app(ObterCompetencia::class)->executar($this->demo, $mes);
    $nota = GeradorXmlNfe::nota($this->demo->cnpj, 'Demo', null, null, $mes->primeiroDia()->setDay(5)->setTime(10, 0), 777, 50);

    $lote = app(ImportarLoteXml::class)->executar($this->demo, $this->carla, [arquivoXml($nota['xml']) => 'nota.xml'], sincrono: true);

    expect($lote->importadas)->toBe(1)
        ->and(NotaFiscal::withoutGlobalScopes()->where('chave_acesso', $nota['chave'])->first()->competencia->toDateString())->toBe($mes->paraBanco());

    $item = ItemCompetencia::withoutGlobalScopes()->where('competencia_id', $competencia->id)->where('nome', 'Notas fiscais de saída')->first();
    expect($item->situacao)->toBe(SituacaoItem::EmAvaliacao);
});

it('recusa nota de competência fechada (RN-724)', function () {
    $fechada = Mes::atual()->anterior()->anterior();
    $nota = GeradorXmlNfe::nota($this->demo->cnpj, 'Demo', null, null, $fechada->primeiroDia()->setDay(10), 888, 50);

    $lote = app(ImportarLoteXml::class)->executar($this->demo, $this->carla, [arquivoXml($nota['xml']) => 'antiga.xml'], sincrono: true);

    expect($lote->rejeitadas)->toBe(1)
        ->and($lote->arquivos()->first()->motivo)->toContain('fechada');
});

it('aplica cancelamento recebido antes da nota (RN-731)', function () {
    $quando = Mes::atual()->primeiroDia()->setDay(4)->setTime(9, 0);
    $nota = GeradorXmlNfe::nota($this->demo->cnpj, 'Demo', null, null, $quando, 999, 70);

    app(ImportarLoteXml::class)->executar($this->demo, $this->carla, [arquivoXml(GeradorXmlNfe::cancelamento($nota['chave'], $quando->addHour())) => 'canc.xml'], sincrono: true);
    app(ImportarLoteXml::class)->executar($this->demo, $this->carla, [arquivoXml($nota['xml']) => 'nota.xml'], sincrono: true);

    expect(NotaFiscal::withoutGlobalScopes()->where('chave_acesso', $nota['chave'])->first()->situacao)->toBe(SituacaoNota::Cancelada);
});

it('só o escritório move nota de competência, com histórico (RN-722)', function () {
    $nota = NotaFiscal::withoutGlobalScopes()->where('empresa_id', $this->demo->id)->whereDate('competencia', Mes::padrao()->paraBanco())->first();
    $this->actingAs($this->carla);

    expect(fn () => app(MoverNotaCompetencia::class)->executar($nota, $this->carla, Mes::atual(), 'teste'))
        ->toThrow(RegraDeNegocioException::class);

    $this->actingAs($this->admin);
    app(MoverNotaCompetencia::class)->executar($nota, $this->admin, Mes::atual(), 'Mercadoria recebida no mês seguinte');

    $nota->refresh();
    expect($nota->mes()->igual(Mes::atual()))->toBeTrue()
        ->and($nota->competencia_original->toDateString())->toBe(Mes::padrao()->paraBanco());
});
