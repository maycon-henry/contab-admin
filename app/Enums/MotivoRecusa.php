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

    /**
     * Motivos que fazem sentido para documentos da admissão (RN-593) — sem os da rotina mensal.
     *
     * @return array<string, string>
     */
    public static function opcoesParaAdmissao(): array
    {
        return collect([self::Ilegivel, self::DocumentoDiferente, self::Vencido, self::Incompleto, self::Outro])
            ->mapWithKeys(fn (self $m) => [$m->value => $m === self::Vencido ? 'Documento vencido ou desatualizado' : $m->getLabel()])->all();
    }

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
