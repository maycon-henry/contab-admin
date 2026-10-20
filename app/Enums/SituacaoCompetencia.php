<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** regras.md RN-630 */
enum SituacaoCompetencia: string implements HasColor, HasLabel
{
    case Aberta = 'aberta';
    case Fechada = 'fechada';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    public function getColor(): string
    {
        return $this === self::Aberta ? 'info' : 'gray';
    }
}
