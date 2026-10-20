<?php

namespace App\Exceptions;

use RuntimeException;

/** Violação de regra de negócio, com mensagem pronta para exibir ao usuário. */
class RegraDeNegocioException extends RuntimeException
{
    /** @var array<string> */
    public array $pendencias = [];

    /** @param array<string> $pendencias */
    public static function comPendencias(string $mensagem, array $pendencias): self
    {
        $e = new self($mensagem);
        $e->pendencias = $pendencias;

        return $e;
    }

    public static function se(bool $condicao, string $mensagem): void
    {
        if ($condicao) {
            throw new self($mensagem);
        }
    }
}
