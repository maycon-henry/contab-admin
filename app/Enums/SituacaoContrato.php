<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** regras.md RN-503 */
enum SituacaoContrato: string implements HasColor, HasLabel
{
    case Rascunho = 'rascunho';
    case EmAnalise = 'em_analise';
    case Devolvida = 'devolvida';
    case Admitido = 'admitido';
    case EmDesligamento = 'em_desligamento';
    case Desligado = 'desligado';
    case AdmissaoCancelada = 'admissao_cancelada';

    public function getLabel(): string
    {
        return match ($this) {
            self::Rascunho => 'Rascunho',
            self::EmAnalise => 'Em análise',
            self::Devolvida => 'Devolvida',
            self::Admitido => 'Admitido',
            self::EmDesligamento => 'Em desligamento',
            self::Desligado => 'Desligado',
            self::AdmissaoCancelada => 'Admissão cancelada',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Rascunho => 'gray',
            self::EmAnalise => 'info',
            self::Devolvida => 'warning',
            self::Admitido => 'success',
            self::EmDesligamento => 'warning',
            self::Desligado => 'gray',
            self::AdmissaoCancelada => 'danger',
        };
    }

    /** Admissão ainda não concluída (RN-529). */
    public function emAdmissao(): bool
    {
        return in_array($this, [self::Rascunho, self::EmAnalise, self::Devolvida], true);
    }

    /** A empresa pode editar dados e documentos (RN-582, RN-588, RN-595). */
    public function editavelPelaEmpresa(): bool
    {
        return in_array($this, [self::Rascunho, self::Devolvida], true);
    }

    /** Contrato que impede o encerramento da empresa (RN-408). */
    public function emAndamento(): bool
    {
        return ! in_array($this, [self::Desligado, self::AdmissaoCancelada], true);
    }
}
