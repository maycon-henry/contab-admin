<?php

namespace App\Notifications;

use App\Models\ItemCompetencia;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** regras.md RN-651 — a empresa é avisada com o motivo */
class ItemRecusadoNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ItemCompetencia $item) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $item = $this->item->loadMissing('competencia');

        return (new MailMessage)
            ->subject("Documento recusado — {$item->nome} ({$item->competencia->rotulo})")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("O documento \"{$item->nome}\" da competência {$item->competencia->rotulo} foi recusado pelo escritório.")
            ->line('Motivo: '.$item->motivo_recusa?->getLabel().($item->complemento_recusa ? " — {$item->complemento_recusa}" : ''))
            ->action('Enviar novamente', url("/portal/{$item->empresa_id}/documentos-do-mes"));
    }
}
