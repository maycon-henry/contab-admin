<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** regras.md RN-732 */
enum SituacaoNota: string implements HasColor, HasLabel
{
    case Autorizada = 'autorizada';
    case Cancelada = 'cancelada';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    public function getColor(): string
    {
        return $this === self::Autorizada ? 'success' : 'danger';
    }
}
