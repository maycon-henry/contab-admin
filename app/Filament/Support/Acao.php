<?php

namespace App\Filament\Support;

use App\Exceptions\RegraDeNegocioException;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

/**
 * Executa uma Action de negócio a partir de uma tela do Filament e mostra o resultado.
 * Regras violadas viram notificação (com a lista de pendências), sem quebrar a tela.
 */
final class Acao
{
    public static function rodar(callable $callback, ?string $sucesso = null, ?Action $action = null): bool
    {
        try {
            $resultado = $callback();
        } catch (RegraDeNegocioException $e) {
            $corpo = $e->pendencias
                ? new HtmlString('<ul style="list-style:disc;padding-left:1rem">'.collect($e->pendencias)->map(fn ($p) => '<li>'.e($p).'</li>')->implode('').'</ul>')
                : null;

            Notification::make()->danger()->title($e->getMessage())->body($corpo)->persistent()->send();
            $action?->halt();

            return false;
        }

        if ($sucesso) {
            $notificacao = Notification::make()->success()->title($sucesso);

            // alertas não impeditivos devolvidos pela Action (ex.: RN-524, RN-589)
            if (is_array($resultado) && $resultado !== []) {
                $notificacao->warning()->body(new HtmlString(collect($resultado)->map(fn ($a) => e($a))->implode('<br>')))->persistent();
            }

            $notificacao->send();
        }

        return true;
    }
}
