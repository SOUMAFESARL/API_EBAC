<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PreInscriptionRecueNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $nomComplet,
        public readonly string $matricule,
        public readonly string $numeroDossier,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirmation de votre demande de pré-inscription à l’EBAC')
            ->view('emails.pre-inscription-recue', [
                'nomComplet' => $this->nomComplet,
                'matricule' => $this->matricule,
                'numeroDossier' => $this->numeroDossier,
            ]);
    }
}
