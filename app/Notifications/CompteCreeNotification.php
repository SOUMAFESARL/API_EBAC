<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CompteCreeNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $motDePasseTemporaire,
        private readonly ?string $anneeAcademique = null,
        private readonly ?string $eglise = null,
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
        $notifiable->loadMissing('role');

        return (new MailMessage)
            ->subject('Création de votre compte EBAC')
            ->view('emails.compte-cree', [
                'nomComplet' => trim("{$notifiable->prenoms} {$notifiable->nom}"),
                'email' => $notifiable->email,
                'motDePasseTemporaire' => $this->motDePasseTemporaire,
                'matricule' => $notifiable->matricule,
                'anneeAcademique' => $this->anneeAcademique,
                'eglise' => $this->eglise,
                'role' => $notifiable->role?->libelle ?? 'Utilisateur',
                'urlConnexion' => rtrim((string) config('app.frontend_url', 'https://ebac.ci'), '/'),
            ]);
    }
}
