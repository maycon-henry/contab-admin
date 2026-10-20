<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** regras.md RN-623 e RN-624 */
enum SituacaoItem: string implements HasColor, HasLabel
{
    case Pendente = 'pendente';
    case EmAvaliacao = 'em_avaliacao';
    case Aceito = 'aceito';
    case Recusado = 'recusado';
    case Concluido = 'concluido';
    case NaoSeAplica = 'nao_se_aplica';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::EmAvaliacao => 'Em avaliação',
            self::Aceito => 'Aceito',
            self::Recusado => 'Recusado',
            self::Concluido => 'Concluído',
            self::NaoSeAplica => 'Não se aplica',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pendente => 'gray',
            self::EmAvaliacao => 'info',
            self::Aceito, self::Concluido => 'success',
            self::Recusado => 'danger',
            self::NaoSeAplica => 'gray',
        };
    }

    /** Situações em que a empresa ainda deve algo (RN-625, RN-1313). */
    public static function cobraveis(): array
    {
        return [self::Pendente, self::Recusado];
    }

    /** Situações que permitem fechar a competência (RN-631). */
    public static function resolvidas(): array
    {
        return [self::Aceito, self::Concluido, self::NaoSeAplica];
    }
}
