<?php

namespace App\Domain\NotasFiscais;

/** Chave de acesso de 44 dígitos da NF-e/NFC-e. */
final class ChaveAcesso
{
    public static function valida(string $chave): bool
    {
        return (bool) preg_match('/^\d{44}$/', $chave)
            && (int) $chave[43] === self::digitoVerificador(substr($chave, 0, 43));
    }

    /** Módulo 11 com pesos de 2 a 9, da direita para a esquerda. */
    public static function digitoVerificador(string $base43): int
    {
        $soma = 0;
        $peso = 2;
        for ($i = strlen($base43) - 1; $i >= 0; $i--) {
            $soma += (int) $base43[$i] * $peso;
            $peso = $peso === 9 ? 2 : $peso + 1;
        }
        $resto = $soma % 11;

        return $resto < 2 ? 0 : 11 - $resto;
    }
}
