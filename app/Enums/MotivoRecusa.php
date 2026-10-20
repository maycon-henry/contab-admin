<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** regras.md RN-651 */
enum MotivoRecusa: string implements HasLabel
{
    case Ilegivel = 'ilegivel';
    case DocumentoDiferente = 'documento_diferente';
    case OutraEmpresa = 'outra_empresa';
    case Vencido = 'vencido';
    case Incompleto = 'incompleto';
    case SemMovimentoNaoConfirmado = 'sem_movimento_nao_confirmado';
    case Outro = 'outro';

    public function getLabel(): string
    {
        return match ($this) {
            self::Ilegivel => 'Documento ilegível',
            self::DocumentoDiferente => 'Documento diferente do solicitado',
            self::OutraEmpresa => 'Documento de outra empresa',
            self::Vencido => 'Documento vencido',
            self::Incompleto => 'Documento incompleto',
            self::SemMovimentoNaoConfirmado => '"Sem movimento" não confirmado',
            self::Outro => 'Outro',
        };
    }
}
