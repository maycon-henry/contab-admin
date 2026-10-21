<?php

namespace App\Actions\Despesas;

use App\Domain\Opcoes;
use App\Enums\Permissao;
use App\Enums\TipoDocumentoDespesa;
use App\Exceptions\RegraDeNegocioException;
use App\Models\ConfigDocumentoDespesa;
use App\Models\User;

/** regras.md RN-1610 — formatos aceitos e tamanho máximo de cada tipo de documento de despesa. */
class SalvarConfigDocumentosDespesa
{
    /** Maior tamanho que o escritório pode configurar, em MB. */
    public const TAMANHO_MAXIMO_PERMITIDO_MB = 50;

    /**
     * @param  array<string, array{formatos: array<string>, tamanho_maximo_mb: int|string}>  $configuracao  chave = tipo
     */
    public function executar(User $user, array $configuracao): void
    {
        RegraDeNegocioException::se(! $user->can(Permissao::EscritorioConfigurar->value), 'Somente o administrador altera estas configurações.');

        foreach (TipoDocumentoDespesa::cases() as $tipo) {
            $dados = $configuracao[$tipo->value] ?? null;
            if ($dados === null) {
                continue;
            }

            $formatos = array_values(array_intersect((array) ($dados['formatos'] ?? []), array_keys(Opcoes::FORMATOS)));
            $tamanho = (int) ($dados['tamanho_maximo_mb'] ?? 0);

            RegraDeNegocioException::se($formatos === [], "{$tipo->getLabel()}: marque pelo menos um formato aceito.");
            RegraDeNegocioException::se($tamanho < 1 || $tamanho > self::TAMANHO_MAXIMO_PERMITIDO_MB,
                "{$tipo->getLabel()}: o tamanho máximo deve ficar entre 1 e ".self::TAMANHO_MAXIMO_PERMITIDO_MB.' MB.');

            ConfigDocumentoDespesa::withoutGlobalScopes()->updateOrCreate(
                ['escritorio_id' => $user->escritorio_id, 'tipo' => $tipo->value],
                ['formatos' => $formatos, 'tamanho_maximo_mb' => $tamanho],
            );
        }
    }
}
