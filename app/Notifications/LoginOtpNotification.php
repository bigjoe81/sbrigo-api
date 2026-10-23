<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoginOtpNotification extends Notification
{
    public function __construct(public readonly string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Il tuo codice di accesso a Sbrigo')
            ->greeting('Accedi a Sbrigo')
            ->line("Il tuo codice di accesso è: {$this->code}")
            ->line('Il codice scade tra 5 minuti. Se non hai richiesto l’accesso, ignora questa email.');
    }
}
