<?php

namespace App\Domain\NotasFiscais;

use App\Domain\Competencia;
use App\Enums\ClassificacaoNota;

/** regras.md RN-713, RN-716 e RN-720. */
class ClassificadorNota
{
    /**
     * @param  array<string>  $cnpjsDaEmpresa  CNPJs dos estabelecimentos (matriz e filiais)
     */
    public function classificar(DadosNotaFiscal $nota, array $cnpjsDaEmpresa): ?ClassificacaoNota
    {
        $ehEmitente = in_array($nota->emitenteDocumento, $cnpjsDaEmpresa, true);
        $ehDestinataria = $nota->destinatarioDocumento !== null && in_array($nota->destinatarioDocumento, $cnpjsDaEmpresa, true);

        if ($ehEmitente) {
            // tpNF: 0 = entrada, 1 = saída
            return $nota->tipoOperacao === '0' ? ClassificacaoNota::EntradaPropria : ClassificacaoNota::Saida;
        }

        if ($ehDestinataria) {
            return ClassificacaoNota::EntradaTerceiros;
        }

        return null;
    }

    /** RN-720/RN-721: mês da data de emissão, no fuso informado na própria nota. */
    public function competencia(DadosNotaFiscal $nota): Competencia
    {
        return Competencia::de((int) $nota->dataEmissao->format('Y'), (int) $nota->dataEmissao->format('m'));
    }
}
