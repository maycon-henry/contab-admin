<?php

namespace App\Domain\Desligamento;

use App\Enums\MotivoDesligamento;
use App\Enums\TipoAviso;
use Carbon\CarbonInterface;

/** regras.md RN-542 e RN-544. */
class CalculadoraAvisoPrevio
{
    /** Dias de aviso: 30 + 3 por ano completo, até 90, na dispensa sem justa causa (Lei 12.506/2011). */
    public function dias(MotivoDesligamento $motivo, CarbonInterface $admissao, CarbonInterface $desligamento): int
    {
        if ($motivo !== MotivoDesligamento::SemJustaCausa) {
            return 30;
        }

        $anosCompletos = (int) $admissao->diffInYears($desligamento);

        return min(30 + 3 * $anosCompletos, 90);
    }

    public function dataProjetadaFimAviso(TipoAviso $tipo, CarbonInterface $desligamento, int $dias): ?CarbonInterface
    {
        return $tipo === TipoAviso::Indenizado ? $desligamento->copy()->addDays($dias) : null;
    }

    /** Pagamento das verbas: 10 dias corridos após o término (art. 477, §6º da CLT). */
    public function prazoPagamento(CarbonInterface $desligamento): CarbonInterface
    {
        return $desligamento->copy()->addDays(10);
    }

    /** Envio do S-2299 ao eSocial: até o 10º dia seguinte ao desligamento. */
    public function prazoEsocial(CarbonInterface $desligamento): CarbonInterface
    {
        return $desligamento->copy()->addDays(10);
    }
}
