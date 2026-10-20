<?php

namespace App\Domain\NotasFiscais;

use App\Exceptions\RegraDeNegocioException;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;

/**
 * Leitor de XML de NF-e (modelo 55), NFC-e (modelo 65) e evento de cancelamento.
 * AGENTS.md seção 5.3: sem resolução de entidades externas e sem DTD.
 */
class LeitorNFe
{
    public function ler(string $conteudo): DadosNotaFiscal|DadosCancelamento
    {
        $xpath = $this->carregar($conteudo);

        if ($this->valor($xpath, '//*[local-name()="infNFe"]/@Id') !== null) {
            return $this->lerNota($xpath);
        }

        if ($this->valor($xpath, '//*[local-name()="evento"]//*[local-name()="tpEvento"]') !== null) {
            return $this->lerCancelamento($xpath);
        }

        throw new RegraDeNegocioException('Arquivo não é uma NF-e, NFC-e ou evento de cancelamento.');
    }

    private function carregar(string $conteudo): DOMXPath
    {
        if (stripos($conteudo, '<!DOCTYPE') !== false || stripos($conteudo, '<!ENTITY') !== false) {
            throw new RegraDeNegocioException('XML inválido (DTD não é permitido).');
        }

        $dom = new DOMDocument;
        $anterior = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($conteudo, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        if (! $ok) {
            throw new RegraDeNegocioException('Arquivo não é um XML válido.');
        }

        return new DOMXPath($dom);
    }

    private function lerNota(DOMXPath $x): DadosNotaFiscal
    {
        $chave = preg_replace('/\D/', '', (string) $this->valor($x, '//*[local-name()="infNFe"]/@Id'));
        $modelo = (string) $this->valor($x, '//*[local-name()="ide"]/*[local-name()="mod"]');

        if (! in_array($modelo, ['55', '65'], true)) {
            throw new RegraDeNegocioException("Modelo de documento não aceito nesta fase ({$modelo}).");
        }

        if (! ChaveAcesso::valida($chave)) {
            throw new RegraDeNegocioException('Chave de acesso inválida.');
        }

        $dataEmissao = $this->valor($x, '//*[local-name()="ide"]/*[local-name()="dhEmi"]');
        if (! $dataEmissao) {
            throw new RegraDeNegocioException('Nota sem data de emissão.');
        }

        $cfops = [];
        foreach ($x->query('//*[local-name()="det"]/*[local-name()="prod"]/*[local-name()="CFOP"]') as $no) {
            $cfops[] = trim($no->textContent);
        }

        $autorizadaEm = $this->valor($x, '//*[local-name()="infProt"]/*[local-name()="dhRecbto"]');

        return new DadosNotaFiscal(
            chave: $chave,
            modelo: $modelo,
            serie: (string) $this->valor($x, '//*[local-name()="ide"]/*[local-name()="serie"]'),
            numero: (string) $this->valor($x, '//*[local-name()="ide"]/*[local-name()="nNF"]'),
            // RN-721: mantém o fuso informado na própria nota
            dataEmissao: CarbonImmutable::parse($dataEmissao),
            tipoOperacao: (string) $this->valor($x, '//*[local-name()="ide"]/*[local-name()="tpNF"]'),
            naturezaOperacao: $this->valor($x, '//*[local-name()="ide"]/*[local-name()="natOp"]'),
            emitenteDocumento: (string) ($this->valor($x, '//*[local-name()="emit"]/*[local-name()="CNPJ"]') ?? $this->valor($x, '//*[local-name()="emit"]/*[local-name()="CPF"]')),
            emitenteNome: (string) $this->valor($x, '//*[local-name()="emit"]/*[local-name()="xNome"]'),
            emitenteUf: $this->valor($x, '//*[local-name()="emit"]//*[local-name()="UF"]'),
            destinatarioDocumento: $this->valor($x, '//*[local-name()="dest"]/*[local-name()="CNPJ"]') ?? $this->valor($x, '//*[local-name()="dest"]/*[local-name()="CPF"]'),
            destinatarioNome: $this->valor($x, '//*[local-name()="dest"]/*[local-name()="xNome"]'),
            destinatarioUf: $this->valor($x, '//*[local-name()="dest"]//*[local-name()="UF"]'),
            cfops: array_values(array_unique($cfops)),
            valorTotal: (string) ($this->valor($x, '//*[local-name()="ICMSTot"]/*[local-name()="vNF"]') ?? '0'),
            valorProdutos: $this->valor($x, '//*[local-name()="ICMSTot"]/*[local-name()="vProd"]'),
            valorIcms: $this->valor($x, '//*[local-name()="ICMSTot"]/*[local-name()="vICMS"]'),
            valorIpi: $this->valor($x, '//*[local-name()="ICMSTot"]/*[local-name()="vIPI"]'),
            protocolo: $this->valor($x, '//*[local-name()="infProt"]/*[local-name()="nProt"]'),
            codigoSituacao: $this->valor($x, '//*[local-name()="infProt"]/*[local-name()="cStat"]'),
            autorizadaEm: $autorizadaEm ? CarbonImmutable::parse($autorizadaEm) : null,
        );
    }

    private function lerCancelamento(DOMXPath $x): DadosCancelamento
    {
        $tipoEvento = $this->valor($x, '//*[local-name()="evento"]//*[local-name()="tpEvento"]');

        if ($tipoEvento !== '110111') {
            throw new RegraDeNegocioException("Evento não aceito nesta fase ({$tipoEvento}). Apenas cancelamento.");
        }

        $chave = (string) $this->valor($x, '//*[local-name()="evento"]//*[local-name()="chNFe"]');
        $quando = $this->valor($x, '//*[local-name()="retEvento"]//*[local-name()="dhRegEvento"]')
            ?? $this->valor($x, '//*[local-name()="evento"]//*[local-name()="dhEvento"]');

        if (! ChaveAcesso::valida($chave)) {
            throw new RegraDeNegocioException('Chave de acesso do cancelamento inválida.');
        }

        return new DadosCancelamento(
            chave: $chave,
            protocolo: $this->valor($x, '//*[local-name()="retEvento"]//*[local-name()="nProt"]'),
            canceladaEm: $quando ? CarbonImmutable::parse($quando) : null,
            codigoSituacao: $this->valor($x, '//*[local-name()="retEvento"]//*[local-name()="cStat"]'),
        );
    }

    private function valor(DOMXPath $x, string $expressao): ?string
    {
        $nos = $x->query($expressao);

        if (! $nos || $nos->length === 0) {
            return null;
        }

        $texto = trim($nos->item(0)->textContent);

        return $texto === '' ? null : $texto;
    }
}
