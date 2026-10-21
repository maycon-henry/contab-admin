<?php

namespace App\Actions\NotasFiscais;

use App\Contracts\GeradorDanfe;
use App\Enums\Permissao;
use App\Enums\SituacaoNota;
use App\Exceptions\RegraDeNegocioException;
use App\Models\NotaFiscal;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** regras.md RN-743 — DANFE (PDF) montado a partir do XML guardado da nota. */
class GerarDanfe
{
    public function __construct(private readonly GeradorDanfe $gerador) {}

    public function executar(NotaFiscal $nota, User $user): string
    {
        $podeVer = $user->ehDaEquipe()
            ? $nota->escritorio_id === $user->escritorio_id && $user->can(Permissao::NotasVisualizar->value)
            : in_array($nota->empresa_id, $user->empresasIds(), true); // RN-744
        if (! $podeVer) {
            throw new AuthorizationException('Você não tem acesso a esta nota fiscal.');
        }

        $xml = Storage::disk(NotaFiscal::DISCO)->get($nota->arquivo);
        RegraDeNegocioException::se(blank($xml), 'O XML desta nota não foi encontrado.');

        try {
            return $this->gerador->gerar($xml, (string) $nota->modelo, $nota->situacao === SituacaoNota::Cancelada);
        } catch (Throwable $e) {
            report($e);

            throw new RegraDeNegocioException('Não foi possível montar o DANFE a partir do XML desta nota.');
        }
    }
}
