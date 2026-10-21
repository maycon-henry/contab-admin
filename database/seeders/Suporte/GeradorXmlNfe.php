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
        $cNF = substr($chave, 35, 8);
        $cDV = substr($chave, 43, 1);
        $tpImp = $modelo === '65' ? '4' : '1';
        $indFinal = $modelo === '65' || ($docDestinatario !== null && strlen($docDestinatario) === 11) ? '1' : '0';
        $indPres = $modelo === '65' ? '1' : '9';
        $endereco = '<xLgr>Rua de Exemplo</xLgr><nro>100</nro><xBairro>Centro</xBairro><cMun>3550308</cMun><xMun>Sao Paulo</xMun><UF>SP</UF><CEP>01001000</CEP><cPais>1058</cPais><xPais>Brasil</xPais>';
        $tagDest = $docDestinatario
            ? '<dest>'.(strlen($docDestinatario) === 14 ? "<CNPJ>{$docDestinatario}</CNPJ>" : "<CPF>{$docDestinatario}</CPF>")
                ."<xNome>{$nomeDestinatario}</xNome><enderDest>{$endereco}</enderDest><indIEDest>9</indIEDest></dest>"
            : '';
        // NFC-e: bloco do QR Code, que a DANFCE imprime
        $supl = $modelo === '65'
            ? "<infNFeSupl><qrCode><![CDATA[https://www.homologacao.nfce.fazenda.sp.gov.br/qrcode?p={$chave}|2|2|1|0]]></qrCode><urlChave>https://www.homologacao.nfce.fazenda.sp.gov.br/consulta</urlChave></infNFeSupl>"
            : '';

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<nfeProc xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">
  <NFe>
    <infNFe Id="NFe{$chave}" versao="4.00">
      <ide><cUF>35</cUF><cNF>{$cNF}</cNF><natOp>{$natureza}</natOp><mod>{$modelo}</mod><serie>1</serie><nNF>{$numero}</nNF><dhEmi>{$dhEmi}</dhEmi><tpNF>{$tipoOperacao}</tpNF><idDest>1</idDest><cMunFG>3550308</cMunFG><tpImp>{$tpImp}</tpImp><tpEmis>1</tpEmis><cDV>{$cDV}</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>{$indFinal}</indFinal><indPres>{$indPres}</indPres><procEmi>0</procEmi><verProc>Exemplo 1.0</verProc></ide>
      <emit><CNPJ>{$cnpjEmitente}</CNPJ><xNome>{$nomeEmitente}</xNome><xFant>{$nomeEmitente}</xFant><enderEmit>{$endereco}<fone>1133334444</fone></enderEmit><IE>123456789</IE><CRT>1</CRT></emit>
      {$tagDest}
      <det nItem="1"><prod><cProd>001</cProd><cEAN>SEM GTIN</cEAN><xProd>Produto de exemplo</xProd><NCM>19052090</NCM><CFOP>{$cfop}</CFOP><uCom>UN</uCom><qCom>1.0000</qCom><vUnCom>{$valorFmt}</vUnCom><vProd>{$valorFmt}</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.0000</qTrib><vUnTrib>{$valorFmt}</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISOutr><CST>99</CST><vBC>0.00</vBC><pPIS>0.00</pPIS><vPIS>0.00</vPIS></PISOutr></PIS><COFINS><COFINSOutr><CST>99</CST><vBC>0.00</vBC><pCOFINS>0.00</pCOFINS><vCOFINS>0.00</vCOFINS></COFINSOutr></COFINS></imposto></det>
      <total><ICMSTot><vBC>0.00</vBC><vICMS>{$icms}</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>{$valorFmt}</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>{$valorFmt}</vNF></ICMSTot></total>
      <transp><modFrete>9</modFrete></transp>
      <pag><detPag><tPag>01</tPag><vPag>{$valorFmt}</vPag></detPag></pag>
      <infAdic><infCpl>Documento de exemplo, sem valor fiscal.</infCpl></infAdic>
    </infNFe>
    {$supl}
  </NFe>
  <protNFe versao="4.00"><infProt><tpAmb>2</tpAmb><verAplic>SP_NFE_PL_009</verAplic><chNFe>{$chave}</chNFe><dhRecbto>{$dhEmi}</dhRecbto><nProt>135{$numero}000001</nProt><digVal>ZXhlbXBsbw==</digVal><cStat>{$cStat}</cStat><xMotivo>Autorizado o uso da NF-e</xMotivo></infProt></protNFe>
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
