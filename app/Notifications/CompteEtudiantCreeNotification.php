<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CompteEtudiantCreeNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $motDePasseTemporaire,
        private readonly ?string $anneeAcademique = null,
        private readonly ?string $eglise = null,
        private readonly ?string $numeroDossier = null,
        private readonly ?string $statutDossier = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre admission et vos accès étudiants EBAC')
            ->view('emails.compte-etudiant-cree', [
                'nomComplet' => trim("{$notifiable->prenoms} {$notifiable->nom}"),
                'email' => $notifiable->email,
                'motDePasseTemporaire' => $this->motDePasseTemporaire,
                'matricule' => $notifiable->matricule,
                'anneeAcademique' => $this->anneeAcademique,
                'eglise' => $this->eglise,
                'numeroDossier' => $this->numeroDossier,
                'statutDossier' => $this->statutDossier === 'Validé' ? 'Complet' : $this->statutDossier,
                'urlConnexion' => rtrim((string) config('app.frontend_url', 'https://ebac.ci'), '/'),
            ]);
    }
}
