<?php

namespace App\Notifications;

use App\Models\Empresa;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** regras.md RN-1315 — conteúdo da cobrança diária */
class CobrancaDocumentacaoNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Empresa $empresa,
        public readonly string $competencia,
        public readonly array $itens,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $total = count($this->itens);

        return (new MailMessage)
            ->subject("Documentos pendentes — {$this->empresa->getFilamentName()} ({$this->competencia})")
            ->markdown('emails.cobranca-documentacao', [
                'nome' => $notifiable->name,
                'empresa' => $this->empresa->getFilamentName(),
                'competencia' => $this->competencia,
                'total' => $total,
                'itens' => $this->itens,
                'url' => url("/portal/{$this->empresa->id}/documentos-do-mes"),
            ]);
    }
}
