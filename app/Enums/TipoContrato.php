<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** regras.md RN-521 */
enum TipoContrato: string implements HasLabel
{
    case Indeterminado = 'indeterminado';
    case Experiencia = 'experiencia';
    case Determinado = 'determinado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Indeterminado => 'Prazo indeterminado',
            self::Experiencia => 'Contrato de experiência',
            self::Determinado => 'Prazo determinado',
        };
    }
}
