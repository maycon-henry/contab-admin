<?php

namespace App\Contracts;

/** Gera a representação impressa (PDF) de uma nota a partir do XML autorizado — regras.md RN-743. */
interface GeradorDanfe
{
    /**
     * @param  string  $xml  XML de distribuição da nota (nota + protocolo)
     * @param  string  $modelo  "55" (NF-e → DANFE) ou "65" (NFC-e → DANFCE)
     * @param  bool  $cancelada  marca o documento como cancelado (RN-730)
     * @return string conteúdo do PDF
     */
    public function gerar(string $xml, string $modelo, bool $cancelada = false): string;
}
