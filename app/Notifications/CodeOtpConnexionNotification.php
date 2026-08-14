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
            ->view('emails.code-otp-connexion', [
                'nomComplet' => trim("{$notifiable->prenoms} {$notifiable->nom}"),
                'code' => $this->code,
                'dureeValiditeMinutes' => $this->dureeValiditeMinutes,
            ]);
    }
}
