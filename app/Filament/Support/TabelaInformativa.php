<?php

namespace App\Filament\Support;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * Tabela simples para janelas informativas (ex.: "Quais documentos", itens de uma cobrança).
 * Cabeçalho destacado, espaço entre colunas e linhas separadas; cores neutras para tema claro e escuro.
 * Textos são escapados; para formatar uma célula, passe um HtmlString.
 */
final class TabelaInformativa
{
    /**
     * @param  array<string>  $colunas  títulos das colunas
     * @param  iterable<array<string|Htmlable|null>>  $linhas  células na ordem das colunas
     */
    public static function html(array $colunas, iterable $linhas, string $vazio = 'Nada para mostrar.'): HtmlString
    {
        $linhas = collect($linhas);

        if ($linhas->isEmpty()) {
            return new HtmlString('<p style="opacity:.75">'.e($vazio).'</p>');
        }

        $borda = 'border-bottom:1px solid rgba(127,127,127,.25)';
        $celula = 'padding:.6rem .9rem;text-align:left;vertical-align:top';

        $cabecalho = collect($colunas)->map(fn (string $c) => "<th style=\"{$celula};{$borda};font-weight:600;white-space:nowrap\">".e($c).'</th>')->implode('');

        $corpo = $linhas->values()->map(fn (array $linha, int $i) => '<tr style="'.($i % 2 ? 'background:rgba(127,127,127,.06)' : '').'">'
            .collect($linha)->map(fn ($valor) => "<td style=\"{$celula};{$borda}\">".($valor instanceof Htmlable ? $valor->toHtml() : e((string) $valor)).'</td>')->implode('')
            .'</tr>')->implode('');

        return new HtmlString(
            '<div style="overflow-x:auto;border:1px solid rgba(127,127,127,.25);border-radius:.5rem">'
            .'<table style="width:100%;border-collapse:collapse;font-size:.875rem">'
            ."<thead style=\"background:rgba(127,127,127,.12)\"><tr>{$cabecalho}</tr></thead>"
            ."<tbody>{$corpo}</tbody></table></div>"
        );
    }

    /** Texto em destaque de alerta (ex.: atraso, recusa). */
    public static function alerta(string $texto): HtmlString
    {
        return new HtmlString('<span style="color:#ef4444;font-weight:500">'.e($texto).'</span>');
    }
}
