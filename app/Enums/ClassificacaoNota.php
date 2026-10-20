<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** regras.md RN-716 */
enum ClassificacaoNota: string implements HasColor, HasLabel
{
    case Saida = 'saida';
    case EntradaTerceiros = 'entrada_terceiros';
    case EntradaPropria = 'entrada_propria';

    public function getLabel(): string
    {
        return match ($this) {
            self::Saida => 'Saída',
            self::EntradaTerceiros => 'Entrada (terceiros)',
            self::EntradaPropria => 'Entrada (emissão própria)',
        };
    }

    public function getColor(): string
    {
        return $this === self::Saida ? 'success' : 'info';
    }

    public function ehEntrada(): bool
    {
        return $this !== self::Saida;
    }
}
