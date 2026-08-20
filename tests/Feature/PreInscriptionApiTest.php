<?php

namespace Tests\Feature;

use App\Models\Eglise;
use App\Notifications\PreInscriptionRecueNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PreInscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_pre_inscription_est_publique_et_cree_le_dossier(): void
    {
        Notification::fake();
        $eglise = Eglise::query()->create([
            'code' => 'EGL-001', 'nom' => 'Église test', 'ville_commune' => 'Cocody',
        ]);

        $reponse = $this->postJson('/api/v1/Etudiant/pre-inscription', [
            'nom' => 'Kouassi',
            'prenoms' => 'Jean Marc',
            'sexe' => 'Masculin',
            'date_naissance' => '2000-05-10',
            'nationalite' => 'Ivoirienne',
            'email' => 'jean@example.com',
            'telephone' => '+2250102030405',
            'adresse' => 'Cocody',
            'eglise_id' => $eglise->id,
            'pieces_requises' => ['Photo', 'Pièce d’identité'],
        ])->assertCreated()
            ->assertJsonPath('message', 'Pré-inscription enregistrée avec succès.')
            ->assertJsonPath('pre_inscription.matricule', 'EBAC-0001-'.now()->year)
            ->assertJsonPath('pre_inscription.numero_dossier', 'KOJ000'.now()->year)
            ->assertJsonPath('pre_inscription.statut', 'Préinscrit')
            ->assertJsonPath('pre_inscription.statut_dossier', 'Incomplet');

        $this->assertDatabaseHas('etudiants', [
            'id' => $reponse->json('pre_inscription.id'), 'nom' => 'Kouassi', 'eglise_id' => $eglise->id,
        ]);
        $this->assertDatabaseHas('dossiers_etudiants', [
            'numero_dossier' => $reponse->json('pre_inscription.numero_dossier'), 'statut' => 'Incomplet',
        ]);
        Notification::assertSentOnDemand(
            PreInscriptionRecueNotification::class,
            fn (PreInscriptionRecueNotification $notification, array $canaux, object $destinataire) =>
                in_array('mail', $canaux, true)
                && $destinataire->routes['mail'] === 'jean@example.com'
                && $notification->matricule === $reponse->json('pre_inscription.matricule'),
        );
    }

    public function test_les_champs_obligatoires_sont_valides_sans_creer_de_donnees(): void
    {
        $this->postJson('/api/v1/Etudiant/pre-inscription', ['email' => 'invalide'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nom', 'prenoms', 'telephone', 'email']);

        $this->assertDatabaseCount('etudiants', 0);
        $this->assertDatabaseCount('dossiers_etudiants', 0);
    }

    public function test_le_matricule_est_sequentiel_pour_l_annee_courante(): void
    {
        Notification::fake();
        \DB::table('etudiants')->insert([
            'matricule' => 'EBAC-0007-'.now()->year,
            'nom' => 'Existant',
            'prenoms' => 'Étudiant',
            'date_inscription' => now()->toDateString(),
        ]);

        $this->postJson('/api/v1/Etudiant/pre-inscription', [
            'nom' => 'Kouadio', 'prenoms' => 'Paul', 'email' => 'paul@example.com', 'telephone' => '+2250700000000',
        ])->assertCreated()
            ->assertJsonPath('pre_inscription.matricule', 'EBAC-0008-'.now()->year);
    }

    public function test_le_numero_de_dossier_est_sequentiel_pour_les_memes_initiales(): void
    {
        Notification::fake();
        $payload = ['nom' => 'Zran', 'prenoms' => 'Marc', 'telephone' => '+2250700000001'];

        $this->postJson('/api/v1/Etudiant/pre-inscription', [...$payload, 'email' => 'marc1@example.com'])
            ->assertCreated()
            ->assertJsonPath('pre_inscription.numero_dossier', 'ZRM000'.now()->year);
        $this->postJson('/api/v1/Etudiant/pre-inscription', [...$payload, 'email' => 'marc2@example.com'])
            ->assertCreated()
            ->assertJsonPath('pre_inscription.numero_dossier', 'ZRM001'.now()->year);
    }

    public function test_le_courriel_de_confirmation_peut_etre_rendu(): void
    {
        $notification = new PreInscriptionRecueNotification(
            nomComplet: 'Marc Zran',
            matricule: 'EBAC-0001-'.now()->year,
            numeroDossier: 'ZRM000'.now()->year,
        );

        $contenu = $notification->toMail(new \stdClass())->render();

        $this->assertStringContainsString('Demande de pré-inscription reçue', $contenu);
        $this->assertStringContainsString('en cours d’analyse', $contenu);
        $this->assertStringContainsString('EBAC-0001-'.now()->year, $contenu);
        $this->assertStringContainsString('ZRM000'.now()->year, $contenu);
    }
}
