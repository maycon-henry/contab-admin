<?php

namespace App\Notifications;

use App\Models\ContatoComercial;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** regras.md RN-004 — aviso à equipe comercial */
class NovoContatoComercialNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ContatoComercial $contato) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $c = $this->contato;

        return (new MailMessage)
            ->subject("Novo pedido de demonstração — {$c->escritorio}")
            ->replyTo($c->email, $c->nome)
            ->line("**Nome:** {$c->nome}")
            ->line("**Escritório:** {$c->escritorio}")
            ->line("**E-mail:** {$c->email}")
            ->line('**Telefone:** '.($c->telefone ?: '—'))
            ->line('**Empresas atendidas:** '.($c->quantidade_empresas ?: '—'))
            ->line('**Mensagem:** '.($c->mensagem ?: '—'));
    }
}
