<?php

namespace App\Actions\Admissao;

use App\Exceptions\RegraDeNegocioException;
use App\Models\Contrato;
use App\Models\User;

/** regras.md RN-592 / RN-551 — transferência não altera quem iniciou o atendimento. */
class TransferirResponsavelAdmissao
{
    public function executar(Contrato $contrato, User $user, User $novoResponsavel): void
    {
        RegraDeNegocioException::se(! $user->ehAdministrador() && $contrato->responsavel_id !== $user->id,
            'Somente o administrador ou o próprio responsável podem transferir a admissão.');
        RegraDeNegocioException::se(! $novoResponsavel->ehDaEquipe() || $novoResponsavel->escritorio_id !== $contrato->escritorio_id,
            'O novo responsável deve ser da equipe do escritório.');

        $anterior = $contrato->responsavel?->name ?? 'ninguém';
        $contrato->update(['responsavel_id' => $novoResponsavel->id]);

        $contrato->registrarHistorico('transferida', "Responsável alterado de {$anterior} para {$novoResponsavel->name}.", $contrato->situacao, $user);
    }
}
