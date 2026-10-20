<?php

namespace App\Domain\NotasFiscais;

use Carbon\CarbonImmutable;

/** Evento de cancelamento (tpEvento 110111) — regras.md seção 10.5. */
final class DadosCancelamento
{
    public function __construct(
        public readonly string $chave,
        public readonly ?string $protocolo,
        public readonly ?CarbonImmutable $canceladaEm,
        public readonly ?string $codigoSituacao,
    ) {}

    /** cStat 135/155 = evento registrado. */
    public function registrado(): bool
    {
        return in_array($this->codigoSituacao, ['135', '155'], true);
    }
}
