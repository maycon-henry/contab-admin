<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** regras.md RN-231 */
class ConviteNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Convite para acessar o sistema — '.$notifiable->escritorio->razao_social)
            ->greeting("Olá, {$notifiable->name}!")
            ->line("Você foi convidado(a) por {$notifiable->escritorio->razao_social} para acessar o sistema.")
            ->action('Criar minha senha', route('convite.mostrar', $this->token))
            ->line('Este link vale por 7 dias. Se você não esperava este convite, ignore este e-mail.');
    }
}
