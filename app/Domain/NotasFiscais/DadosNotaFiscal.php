<?php

namespace App\Domain\NotasFiscais;

use Carbon\CarbonImmutable;

/** Dados extraídos de um XML de NF-e/NFC-e (regras.md RN-710). */
final class DadosNotaFiscal
{
    public function __construct(
        public readonly string $chave,
        public readonly string $modelo,
        public readonly string $serie,
        public readonly string $numero,
        public readonly CarbonImmutable $dataEmissao,
        public readonly string $tipoOperacao,
        public readonly ?string $naturezaOperacao,
        public readonly string $emitenteDocumento,
        public readonly string $emitenteNome,
        public readonly ?string $emitenteUf,
        public readonly ?string $destinatarioDocumento,
        public readonly ?string $destinatarioNome,
        public readonly ?string $destinatarioUf,
        public readonly array $cfops,
        public readonly string $valorTotal,
        public readonly ?string $valorProdutos,
        public readonly ?string $valorIcms,
        public readonly ?string $valorIpi,
        public readonly ?string $protocolo,
        public readonly ?string $codigoSituacao,
        public readonly ?CarbonImmutable $autorizadaEm,
    ) {}

    /** cStat 100 (autorizada) e 150 (autorizada fora de prazo). */
    public function autorizada(): bool
    {
        return in_array($this->codigoSituacao, ['100', '150'], true);
    }
}
