<?php

namespace App\Notifications;

use App\Models\Contrato;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** regras.md RN-576 — aviso ao escritório */
class AdmissaoSolicitadaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Contrato $contrato) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $c = $this->contrato->loadMissing('funcionario', 'empresa');

        return (new MailMessage)
            ->subject("Nova admissão solicitada — {$c->funcionario->nome}")
            ->line("{$c->empresa->razao_social} solicitou a admissão de {$c->funcionario->nome} ({$c->cargo}).")
            ->line('Início previsto: '.$c->data_admissao_prevista?->format('d/m/Y').'. O registro no eSocial deve ser feito até o dia anterior.')
            ->action('Abrir fila de admissões', url('/admin/admissoes'));
    }
}
