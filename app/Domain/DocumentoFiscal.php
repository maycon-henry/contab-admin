<?php

namespace App\Domain;

/** Validação e formatação de CPF/CNPJ (regras.md RN-400, RN-510). */
final class DocumentoFiscal
{
    public static function somenteDigitos(?string $valor): string
    {
        return preg_replace('/\D/', '', (string) $valor);
    }

    public static function cpfValido(?string $cpf): bool
    {
        $cpf = self::somenteDigitos($cpf);

        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $soma = 0;
            for ($i = 0; $i < $t; $i++) {
                $soma += (int) $cpf[$i] * (($t + 1) - $i);
            }
            $digito = ((10 * $soma) % 11) % 10;
            if ((int) $cpf[$t] !== $digito) {
                return false;
            }
        }

        return true;
    }

    public static function cnpjValido(?string $cnpj): bool
    {
        $cnpj = self::somenteDigitos($cnpj);

        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        return substr($cnpj, 12, 2) === self::digitosCnpj(substr($cnpj, 0, 12));
    }

    public static function digitosCnpj(string $base12): string
    {
        $pesos1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $pesos2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        $d1 = self::modulo11($base12, $pesos1);
        $d2 = self::modulo11($base12.$d1, $pesos2);

        return $d1.$d2;
    }

    public static function digitosCpf(string $base9): string
    {
        $d1 = self::modulo11($base9, range(10, 2));
        $d2 = self::modulo11($base9.$d1, range(11, 2));

        return $d1.$d2;
    }

    private static function modulo11(string $numero, array $pesos): int
    {
        $soma = 0;
        foreach ($pesos as $i => $peso) {
            $soma += (int) $numero[$i] * $peso;
        }
        $resto = $soma % 11;

        return $resto < 2 ? 0 : 11 - $resto;
    }

    public static function formatarCnpj(?string $cnpj): string
    {
        $cnpj = self::somenteDigitos($cnpj);

        return strlen($cnpj) === 14
            ? vsprintf('%s.%s.%s/%s-%s', [substr($cnpj, 0, 2), substr($cnpj, 2, 3), substr($cnpj, 5, 3), substr($cnpj, 8, 4), substr($cnpj, 12, 2)])
            : (string) $cnpj;
    }

    public static function formatarCpf(?string $cpf): string
    {
        $cpf = self::somenteDigitos($cpf);

        return strlen($cpf) === 11
            ? vsprintf('%s.%s.%s-%s', [substr($cpf, 0, 3), substr($cpf, 3, 3), substr($cpf, 6, 3), substr($cpf, 9, 2)])
            : (string) $cpf;
    }

    public static function formatar(?string $documento): string
    {
        $documento = self::somenteDigitos($documento);

        return strlen($documento) === 14 ? self::formatarCnpj($documento) : self::formatarCpf($documento);
    }
}
