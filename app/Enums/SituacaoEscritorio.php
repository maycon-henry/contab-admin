<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** regras.md RN-103 */
enum SituacaoEscritorio: string implements HasLabel
{
    case Ativo = 'ativo';
    case Suspenso = 'suspenso';
    case Cancelado = 'cancelado';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }
}
