<?php

namespace App\Filament\Compartilhado;

use Filament\Forms\Components\TextInput;
use Filament\Support\RawJs;

/**
 * Campo de valor em reais com máscara 1.234,56 (AGENTS.md seção 9 — componente reutilizável).
 * A tela trabalha com o texto formatado; para o backend vai sempre o decimal "1234.56".
 */
final class CampoMoeda
{
    public static function make(string $nome): TextInput
    {
        return TextInput::make($nome)
            ->prefix('R$')
            ->inputMode('decimal')
            ->placeholder('0,00')
            ->mask(RawJs::make("\$money(\$input, ',', '.', 2)"))
            ->formatStateUsing(fn ($state) => filled($state) ? number_format((float) $state, 2, ',', '.') : null)
            ->dehydrateStateUsing(fn ($state) => self::paraDecimal($state))
            ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                $decimal = self::paraDecimal($value);
                if ($decimal === null || (float) $decimal <= 0) {
                    $fail('Informe um valor maior que zero.');
                }
            });
    }

    /** "1.234,56" → "1234.56"; aceita também o valor já em decimal. */
    public static function paraDecimal(mixed $valor): ?string
    {
        if (blank($valor)) {
            return null;
        }

        $texto = (string) $valor;
        if (str_contains($texto, ',')) {
            $texto = str_replace(['.', ','], ['', '.'], $texto);
        }

        return is_numeric($texto) ? number_format((float) $texto, 2, '.', '') : null;
    }
}
