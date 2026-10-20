<?php

namespace App\Notifications;

use App\Models\Contrato;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** regras.md RN-576 — aviso à empresa: devolvida, cancelada ou concluída */
class AdmissaoAtualizadaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Contrato $contrato, public readonly string $evento) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $c = $this->contrato->loadMissing('funcionario');

        $mensagem = (new MailMessage)->greeting("Olá, {$notifiable->name}!");

        return match ($this->evento) {
            'devolvida' => $mensagem->subject("Admissão devolvida para correção — {$c->funcionario->nome}")
                ->line("A admissão de {$c->funcionario->nome} foi devolvida pelo escritório.")
                ->line("Motivo: {$c->motivo_devolucao}")
                ->action('Corrigir a admissão', url("/portal/{$c->empresa_id}/admissoes/{$c->id}/edit")),
            'cancelada' => $mensagem->subject("Admissão cancelada — {$c->funcionario->nome}")
                ->line("A admissão de {$c->funcionario->nome} foi cancelada pelo escritório.")
                ->line("Justificativa: {$c->motivo_cancelamento}"),
            default => $mensagem->subject("Funcionário admitido — {$c->funcionario->nome}")
                ->line("A admissão de {$c->funcionario->nome} foi concluída pelo escritório.")
                ->action('Ver admissão', url("/portal/{$c->empresa_id}/admissoes/{$c->id}")),
        };
    }
}
