<?php

namespace App\Actions\NotasFiscais;

use App\Actions\Competencias\ObterCompetencia;
use App\Domain\Competencia as Mes;
use App\Enums\Permissao;
use App\Exceptions\RegraDeNegocioException;
use App\Models\NotaFiscal;
use App\Models\User;

/** regras.md RN-722 — só o escritório move notas, com justificativa e histórico. */
class MoverNotaCompetencia
{
    public function __construct(private readonly ObterCompetencia $obterCompetencia) {}

    public function executar(NotaFiscal $nota, User $user, Mes $destino, string $justificativa): void
    {
        RegraDeNegocioException::se(! $user->can(Permissao::NotasGerenciar->value), 'Somente o escritório pode mover notas (RN-722).');
        RegraDeNegocioException::se(blank($justificativa), 'Informe a justificativa.');
        RegraDeNegocioException::se($nota->mes()->igual($destino), 'A nota já está nesta competência.');

        $origem = $this->obterCompetencia->executar($nota->empresa, $nota->mes());
        $novo = $this->obterCompetencia->executar($nota->empresa, $destino);
        RegraDeNegocioException::se($origem->estaFechada() || $novo->estaFechada(), 'A competência de origem ou de destino está fechada.');

        $nota->update([
            'competencia_original' => $nota->competencia_original ?? $nota->competencia,
            'competencia' => $destino->paraBanco(),
            'justificativa_mudanca' => $justificativa,
        ]);
    }
}
