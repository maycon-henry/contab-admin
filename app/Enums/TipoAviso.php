<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** regras.md RN-540 */
enum TipoAviso: string implements HasLabel
{
    case Trabalhado = 'trabalhado';
    case Indenizado = 'indenizado';
    case Dispensado = 'dispensado';
    case NaoSeAplica = 'nao_se_aplica';

    public function getLabel(): string
    {
        return match ($this) {
            self::Trabalhado => 'Trabalhado',
            self::Indenizado => 'Indenizado',
            self::Dispensado => 'Dispensado',
            self::NaoSeAplica => 'Não se aplica',
        };
    }
}
