<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RegimeTributario: string implements HasLabel
{
    case Mei = 'mei';
    case SimplesNacional = 'simples_nacional';
    case LucroPresumido = 'lucro_presumido';
    case LucroReal = 'lucro_real';

    public function getLabel(): string
    {
        return match ($this) {
            self::Mei => 'MEI',
            self::SimplesNacional => 'Simples Nacional',
            self::LucroPresumido => 'Lucro Presumido',
            self::LucroReal => 'Lucro Real',
        };
    }
}
