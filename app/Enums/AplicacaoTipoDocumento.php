<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** regras.md RN-610 (aplicação) */
enum AplicacaoTipoDocumento: string implements HasLabel
{
    case Todas = 'todas';
    case PorRegime = 'por_regime';
    case Selecionadas = 'selecionadas';

    public function getLabel(): string
    {
        return match ($this) {
            self::Todas => 'Todas as empresas',
            self::PorRegime => 'Por regime tributário',
            self::Selecionadas => 'Empresas selecionadas',
        };
    }
}
