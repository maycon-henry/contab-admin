<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** regras.md RN-653 */
enum ResultadoCertidao: string implements HasColor, HasLabel
{
    case Negativa = 'negativa';
    case PositivaComEfeitosDeNegativa = 'positiva_efeitos_negativa';
    case Positiva = 'positiva';

    public function getLabel(): string
    {
        return match ($this) {
            self::Negativa => 'Negativa',
            self::PositivaComEfeitosDeNegativa => 'Positiva com efeitos de negativa',
            self::Positiva => 'Positiva',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Negativa => 'success',
            self::PositivaComEfeitosDeNegativa => 'warning',
            self::Positiva => 'danger',
        };
    }
}
