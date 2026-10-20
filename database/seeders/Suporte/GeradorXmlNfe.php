<?php

namespace Database\Seeders\Suporte;

use App\Domain\NotasFiscais\ChaveAcesso;
use Carbon\CarbonImmutable;

/**
 * Gera XMLs de NF-e/NFC-e fictícios (estrutura nfeProc com protocolo) para dados de exemplo e testes.
 * Não são documentos fiscais válidos: não têm assinatura digital.
 */
final class GeradorXmlNfe
{
    public static function nota(
        string $cnpjEmitente,
        string $nomeEmitente,
        ?string $docDestinatario,
        ?string $nomeDestinatario,
        CarbonImmutable $emissao,
        int $numero,
        float $valor,
        string $tipoOperacao = '1',
        string $modelo = '55',
        string $cfop = '5102',
        string $cStat = '100',
        string $natureza = 'Venda de mercadoria',
    ): array {
        $chave = self::chave($cnpjEmitente, $emissao, $modelo, 1, $numero);
        $dhEmi = $emissao->format('Y-m-d\TH:i:sP');
        $valorFmt = number_format($valor, 2, '.', '');
        $icms = number_format($valor * 0.18, 2, '.', '');
        $tagDest = $docDestinatario
            ? '<dest>'.(strlen($docDestinatario) === 14 ? "<CNPJ>{$docDestinatario}</CNPJ>" : "<CPF>{$docDestinatario}</CPF>")
                ."<xNome>{$nomeDestinatario}</xNome><enderDest><xMun>Sao Paulo</xMun><UF>SP</UF></enderDest></dest>"
            : '';

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<nfeProc xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">
  <NFe>
    <infNFe Id="NFe{$chave}" versao="4.00">
      <ide><cUF>35</cUF><natOp>{$natureza}</natOp><mod>{$modelo}</mod><serie>1</serie><nNF>{$numero}</nNF><dhEmi>{$dhEmi}</dhEmi><tpNF>{$tipoOperacao}</tpNF></ide>
      <emit><CNPJ>{$cnpjEmitente}</CNPJ><xNome>{$nomeEmitente}</xNome><enderEmit><xMun>Sao Paulo</xMun><UF>SP</UF></enderEmit><IE>123456789</IE></emit>
      {$tagDest}
      <det nItem="1"><prod><cProd>001</cProd><xProd>Produto de exemplo</xProd><NCM>19052090</NCM><CFOP>{$cfop}</CFOP><qCom>1</qCom><vProd>{$valorFmt}</vProd></prod></det>
      <total><ICMSTot><vProd>{$valorFmt}</vProd><vICMS>{$icms}</vICMS><vIPI>0.00</vIPI><vNF>{$valorFmt}</vNF></ICMSTot></total>
    </infNFe>
  </NFe>
  <protNFe versao="4.00"><infProt><tpAmb>2</tpAmb><chNFe>{$chave}</chNFe><dhRecbto>{$dhEmi}</dhRecbto><nProt>135{$numero}000001</nProt><cStat>{$cStat}</cStat><xMotivo>Autorizado o uso da NF-e</xMotivo></infProt></protNFe>
</nfeProc>
XML;

        return ['chave' => $chave, 'xml' => $xml];
    }

    public static function cancelamento(string $chave, CarbonImmutable $quando): string
    {
        $dh = $quando->format('Y-m-d\TH:i:sP');

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<procEventoNFe xmlns="http://www.portalfiscal.inf.br/nfe" versao="1.00">
  <evento versao="1.00"><infEvento Id="ID110111{$chave}01"><chNFe>{$chave}</chNFe><dhEvento>{$dh}</dhEvento><tpEvento>110111</tpEvento><detEvento versao="1.00"><descEvento>Cancelamento</descEvento><xJust>Erro na emissao da nota</xJust></detEvento></infEvento></evento>
  <retEvento versao="1.00"><infEvento><cStat>135</cStat><xMotivo>Evento registrado</xMotivo><chNFe>{$chave}</chNFe><dhRegEvento>{$dh}</dhRegEvento><nProt>135999000001</nProt></infEvento></retEvento>
</procEventoNFe>
XML;
    }

    /** cUF(2) + AAMM(4) + CNPJ(14) + mod(2) + serie(3) + nNF(9) + tpEmis(1) + cNF(8) + DV(1) */
    public static function chave(string $cnpj, CarbonImmutable $emissao, string $modelo, int $serie, int $numero): string
    {
        $base = '35'.$emissao->format('ym').$cnpj.$modelo.str_pad((string) $serie, 3, '0', STR_PAD_LEFT)
            .str_pad((string) $numero, 9, '0', STR_PAD_LEFT).'1'.str_pad((string) ($numero * 7 % 99999999), 8, '0', STR_PAD_LEFT);

        return $base.ChaveAcesso::digitoVerificador($base);
    }
}
