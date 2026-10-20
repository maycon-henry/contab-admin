<?php

namespace App\Domain\Rotina;

use App\Enums\AplicacaoTipoDocumento;
use App\Models\TipoDocumento;

final class ItemDaRotina
{
    public function __construct(
        public readonly TipoDocumento $tipo,
        public readonly int $prazoDia,
        public readonly bool $obrigatorio,
        public readonly bool $dispensado,
        public readonly ?string $justificativaDispensa,
        public readonly bool $ajustado,
    ) {}

    public function origem(): string
    {
        return match ($this->tipo->aplicacao) {
            AplicacaoTipoDocumento::Todas => 'Todas as empresas',
            AplicacaoTipoDocumento::PorRegime => 'Regime tributário',
            AplicacaoTipoDocumento::Selecionadas => 'Empresa selecionada',
        };
    }
}
