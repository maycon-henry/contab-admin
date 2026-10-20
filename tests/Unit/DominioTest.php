<?php

use App\Domain\Competencia;
use App\Domain\Desligamento\CalculadoraAvisoPrevio;
use App\Domain\DocumentoFiscal;
use App\Domain\NotasFiscais\ChaveAcesso;
use App\Domain\NotasFiscais\ClassificadorNota;
use App\Domain\NotasFiscais\DadosCancelamento;
use App\Domain\NotasFiscais\LeitorNFe;
use App\Enums\ClassificacaoNota;
use App\Enums\MotivoDesligamento;
use App\Enums\TipoAviso;
use App\Exceptions\RegraDeNegocioException;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Seeders\Suporte\GeradorXmlNfe;

describe('Competencia (AGENTS.md 3.5)', function () {
    it('converte entre URL, tela e banco', function () {
        $c = Competencia::parse('2026-09');

        expect($c->paraTela())->toBe('09/2026')
            ->and($c->paraUrl())->toBe('2026-09')
            ->and($c->paraBanco())->toBe('2026-09-01')
            ->and(Competencia::parse('09/2026')->igual($c))->toBeTrue();
    });

    it('navega entre meses e calcula o prazo no mês seguinte (RN-610)', function () {
        $c = Competencia::de(2026, 12);

        expect($c->seguinte()->paraUrl())->toBe('2027-01')
            ->and($c->anterior()->paraUrl())->toBe('2026-11')
            ->and(Competencia::de(2026, 1)->diaDoMesSeguinte(31)->toDateString())->toBe('2026-02-28');
    });

    it('usa o mês anterior como competência padrão (decisão D11)', function () {
        Carbon::setTestNow('2026-10-19');

        expect(Competencia::padrao()->paraTela())->toBe('09/2026')
            ->and(Competencia::de(2026, 11)->ehFutura())->toBeTrue();
    });
});

describe('Documentos fiscais', function () {
    it('valida CPF e CNPJ', function () {
        expect(DocumentoFiscal::cnpjValido('11.222.333/0001-81'))->toBeTrue()
            ->and(DocumentoFiscal::cnpjValido('11.222.333/0001-82'))->toBeFalse()
            ->and(DocumentoFiscal::cpfValido('529.982.247-25'))->toBeTrue()
            ->and(DocumentoFiscal::cpfValido('111.111.111-11'))->toBeFalse();
    });

    it('valida o dígito da chave de acesso', function () {
        $chave = GeradorXmlNfe::chave('11222333000181', CarbonImmutable::parse('2026-09-10'), '55', 1, 123);

        expect(ChaveAcesso::valida($chave))->toBeTrue()
            ->and(ChaveAcesso::valida(substr($chave, 0, 43).((int) $chave[43] + 1) % 10))->toBeFalse();
    });
});

describe('Aviso prévio (RN-542)', function () {
    it('soma 3 dias por ano completo na dispensa sem justa causa, até 90', function () {
        $calc = new CalculadoraAvisoPrevio;

        expect($calc->dias(MotivoDesligamento::SemJustaCausa, Carbon::parse('2022-03-01'), Carbon::parse('2026-10-10')))->toBe(42)
            ->and($calc->dias(MotivoDesligamento::SemJustaCausa, Carbon::parse('1990-01-01'), Carbon::parse('2026-10-10')))->toBe(90)
            ->and($calc->dias(MotivoDesligamento::PedidoDeDemissao, Carbon::parse('2022-03-01'), Carbon::parse('2026-10-10')))->toBe(30);
    });

    it('projeta o fim do aviso indenizado', function () {
        $calc = new CalculadoraAvisoPrevio;

        expect($calc->dataProjetadaFimAviso(TipoAviso::Indenizado, Carbon::parse('2026-10-10'), 42)->toDateString())->toBe('2026-11-21')
            ->and($calc->dataProjetadaFimAviso(TipoAviso::Trabalhado, Carbon::parse('2026-10-10'), 42))->toBeNull();
    });
});

describe('Leitura e classificação de NF-e (seção 10)', function () {
    $empresa = '11222333000181';

    it('classifica saída, entrada de terceiros e entrada própria (RN-716)', function () use ($empresa) {
        $leitor = new LeitorNFe;
        $classificador = new ClassificadorNota;
        $quando = CarbonImmutable::parse('2026-09-10 10:00');

        $saida = $leitor->ler(GeradorXmlNfe::nota($empresa, 'Empresa', null, null, $quando, 1, 100)['xml']);
        $entrada = $leitor->ler(GeradorXmlNfe::nota('22333444000181', 'Fornecedor', $empresa, 'Empresa', $quando, 2, 100)['xml']);
        $propria = $leitor->ler(GeradorXmlNfe::nota($empresa, 'Empresa', null, null, $quando, 3, 100, '0')['xml']);
        $outra = $leitor->ler(GeradorXmlNfe::nota('22333444000181', 'X', '33444555000181', 'Y', $quando, 4, 100)['xml']);

        expect($classificador->classificar($saida, [$empresa]))->toBe(ClassificacaoNota::Saida)
            ->and($classificador->classificar($entrada, [$empresa]))->toBe(ClassificacaoNota::EntradaTerceiros)
            ->and($classificador->classificar($propria, [$empresa]))->toBe(ClassificacaoNota::EntradaPropria)
            ->and($classificador->classificar($outra, [$empresa]))->toBeNull();
    });

    it('usa o mês da emissão no fuso da própria nota (RN-721)', function () use ($empresa) {
        $xml = GeradorXmlNfe::nota($empresa, 'Empresa', null, null, CarbonImmutable::parse('2026-09-30 23:30', 'America/Sao_Paulo'), 1, 10)['xml'];
        $nota = (new LeitorNFe)->ler($xml);

        // em UTC já seria 01/10, mas a nota pertence a setembro
        expect($nota->dataEmissao->utc()->format('m'))->toBe('10')
            ->and((new ClassificadorNota)->competencia($nota)->paraTela())->toBe('09/2026');
    });

    it('lê o evento de cancelamento', function () use ($empresa) {
        $chave = GeradorXmlNfe::chave($empresa, CarbonImmutable::parse('2026-09-10'), '55', 1, 7);
        $evento = (new LeitorNFe)->ler(GeradorXmlNfe::cancelamento($chave, CarbonImmutable::parse('2026-09-11')));

        expect($evento)->toBeInstanceOf(DadosCancelamento::class)
            ->and($evento->chave)->toBe($chave)
            ->and($evento->registrado())->toBeTrue();
    });

    it('recusa XML com DTD/entidades (AGENTS.md 5.3) e arquivos inválidos', function () {
        $leitor = new LeitorNFe;

        expect(fn () => $leitor->ler('<?xml version="1.0"?><!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]><x>&e;</x>'))
            ->toThrow(RegraDeNegocioException::class, 'DTD')
            ->and(fn () => $leitor->ler('não é xml'))->toThrow(RegraDeNegocioException::class);
    });
});
