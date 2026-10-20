<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** regras.md RN-402 */
enum SituacaoEmpresa: string implements HasColor, HasLabel
{
    case Ativa = 'ativa';
    case Suspensa = 'suspensa';
    case Encerrada = 'encerrada';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Ativa => 'success',
            self::Suspensa => 'warning',
            self::Encerrada => 'gray',
        };
    }
}
