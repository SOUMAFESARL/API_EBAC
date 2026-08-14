<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReinitialisationMotDePasseNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $token,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.frontend_url'), '/')
            .'/reset-password?token='.urlencode($this->token)
            .'&email='.urlencode($notifiable->getEmailForPasswordReset());

        return (new MailMessage)
            ->subject('Réinitialisation de votre mot de passe EBAC')
            ->view('emails.reinitialisation-mot-de-passe', [
                'nomComplet' => trim("{$notifiable->prenoms} {$notifiable->nom}"),
                'urlReinitialisation' => $url,
                'expiration' => (int) config('auth.passwords.users.expire', 60),
            ]);
    }
}
