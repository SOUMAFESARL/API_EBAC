<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CodeOtpConnexionNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $code,
        private readonly int $dureeValiditeMinutes = 10,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre code de connexion EBAC')
            ->greeting('Bonjour '.trim("{$notifiable->prenoms} {$notifiable->nom}"))
            ->line('Voici votre code de confirmation :')
            ->line($this->code)
            ->line("Ce code expire dans {$this->dureeValiditeMinutes} minutes.")
            ->line("Si vous n'êtes pas à l'origine de cette demande, ignorez cet e-mail.");
    }
}
