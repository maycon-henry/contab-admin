<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** regras.md RN-610 (tipo) */
enum TipoItem: string implements HasLabel
{
    case Documento = 'documento';
    case Tarefa = 'tarefa';

    public function getLabel(): string
    {
        return match ($this) {
            self::Documento => 'Documento (enviado pela empresa)',
            self::Tarefa => 'Tarefa (executada pelo escritório)',
        };
    }
}
