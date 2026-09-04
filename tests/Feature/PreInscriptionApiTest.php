<?php

namespace Tests\Feature;

use App\Models\Civilite;
use App\Models\Eglise;
use App\Notifications\PreInscriptionRecueNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PreInscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_pre_inscription_est_publique_et_cree_le_dossier(): void
    {
        Notification::fake();
        Storage::fake('public');
        $civilite = Civilite::query()->create(['code' => 'M', 'name' => 'Monsieur']);
        $eglise = Eglise::query()->create([
            'code' => 'EGL-001', 'nom' => 'Église test', 'ville_commune' => 'Cocody',
        ]);

        $reponse = $this->postJson('/api/v1/etudiant/pre-inscription', [
            'nom' => 'Kouassi',
            'prenoms' => 'Jean Marc',
            'civilite_id' => $civilite->id,
            'photo_identite' => UploadedFile::fake()->image('identite.jpg'),
            'documents' => [
                UploadedFile::fake()->create('piece-identite.pdf', 500, 'application/pdf'),
                UploadedFile::fake()->create('diplome.pdf', 750, 'application/pdf'),
            ],
            'date_naissance' => '2000-05-10',
            'nationalite' => 'Ivoirienne',
            'email' => 'jean@example.com',
            'telephone' => '+2250102030405',
            'adresse' => 'Cocody',
            'situation_matrimonial' => 'Marié',
            'nombre_enfant' => 2,
            'eglise_id' => $eglise->id,
            'pieces_requises' => ['Photo', 'Pièce d’identité'],
        ])->assertCreated()
            ->assertJsonPath('message', 'Pré-inscription enregistrée avec succès.')
            ->assertJsonPath('pre_inscription.numero_dossier', 'KOJ000'.now()->year)
            ->assertJsonPath('pre_inscription.statut', 'Préinscrit')
            ->assertJsonPath('pre_inscription.statut_dossier', 'Incomplet')
            ->assertJsonPath('pre_inscription.situation_matrimonial', 'Marié')
            ->assertJsonPath('pre_inscription.nombre_enfant', 2);
        $reponse->assertJsonPath('pre_inscription.nombre_documents', 2);
        $reponse->assertJsonCount(2, 'pre_inscription.documents');
        $reponse->assertJsonMissingPath('pre_inscription.matricule');
        $this->assertStringContainsString(
            '/api/v1/fichiers-preinscriptions/etudiants/photos-identite/',
            $reponse->json('pre_inscription.photo_identite_url'),
        );
        foreach ($reponse->json('pre_inscription.documents') as $document) {
            $this->assertStringContainsString('/api/v1/fichiers-preinscriptions/etudiants/dossiers/', $document['url']);
            $this->assertNotEmpty($document['nom_original']);
        }

        $this->get($reponse->json('pre_inscription.photo_identite_url'))
            ->assertUnauthorized();
        $this->get($reponse->json('pre_inscription.documents.0.url'))
            ->assertUnauthorized();

        $this->assertDatabaseHas('etudiants', [
            'id' => $reponse->json('pre_inscription.id'), 'nom' => 'Kouassi', 'eglise_id' => $eglise->id,
            'civilite_id' => $civilite->id,
            'situation_matrimonial' => 'Marié', 'nombre_enfant' => 2,
        ]);
        Storage::disk('public')->assertExists(
            \DB::table('etudiants')->where('id', $reponse->json('pre_inscription.id'))->value('photo_identite'),
        );
        $this->assertDatabaseHas('dossiers_etudiants', [
            'numero_dossier' => $reponse->json('pre_inscription.numero_dossier'), 'statut' => 'Incomplet',
        ]);
        $this->assertDatabaseCount('fichiers_dossiers_etudiants', 2);
        foreach (\DB::table('fichiers_dossiers_etudiants')->pluck('chemin') as $chemin) {
            Storage::disk('public')->assertExists($chemin);
        }
        Notification::assertSentOnDemand(
            PreInscriptionRecueNotification::class,
            fn (PreInscriptionRecueNotification $notification, array $canaux, object $destinataire) => in_array('mail', $canaux, true)
                && $destinataire->routes['mail'] === 'jean@example.com'
                && $notification->numeroDossier === $reponse->json('pre_inscription.numero_dossier'),
        );
    }

    public function test_les_champs_obligatoires_sont_valides_sans_creer_de_donnees(): void
    {
        $this->postJson('/api/v1/etudiant/pre-inscription', ['email' => 'invalide'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nom', 'prenoms', 'civilite_id', 'telephone', 'email', 'eglise_id', 'photo_identite', 'situation_matrimonial']);

        $this->assertDatabaseCount('etudiants', 0);
        $this->assertDatabaseCount('dossiers_etudiants', 0);
    }

    public function test_la_preinscription_ne_genere_pas_de_matricule(): void
    {
        Notification::fake();
        Storage::fake('public');
        $civilite = Civilite::query()->create(['code' => 'M', 'name' => 'Monsieur']);
        $eglise = Eglise::query()->create([
            'code' => 'EGL-001', 'nom' => 'Église test', 'ville_commune' => 'Cocody',
        ]);
        $id = $this->postJson('/api/v1/etudiant/pre-inscription', [
            'nom' => 'Kouadio', 'prenoms' => 'Paul', 'email' => 'paul@example.com', 'telephone' => '+2250700000000',
            'eglise_id' => $eglise->id, 'civilite_id' => $civilite->id,
            'situation_matrimonial' => 'Célibataire',
            'photo_identite' => UploadedFile::fake()->image('identite.jpg'),
        ])->assertCreated()
            ->assertJsonMissingPath('pre_inscription.matricule')
            ->json('pre_inscription.id');

        $matriculeTechnique = \DB::table('etudiants')->where('id', $id)->value('matricule');
        $this->assertIsString($matriculeTechnique);
        $this->assertStringStartsWith('PRE-', $matriculeTechnique);
    }

    public function test_le_numero_de_dossier_est_sequentiel_pour_les_memes_initiales(): void
    {
        Notification::fake();
        Storage::fake('public');
        $civilite = Civilite::query()->create(['code' => 'M', 'name' => 'Monsieur']);
        $eglise = Eglise::query()->create([
            'code' => 'EGL-001', 'nom' => 'Église test', 'ville_commune' => 'Cocody',
        ]);
        $payload = ['nom' => 'Zran', 'prenoms' => 'Marc', 'telephone' => '+2250700000001', 'eglise_id' => $eglise->id, 'civilite_id' => $civilite->id, 'situation_matrimonial' => 'Marié'];

        $this->postJson('/api/v1/etudiant/pre-inscription', [...$payload, 'email' => 'marc1@example.com', 'photo_identite' => UploadedFile::fake()->image('identite1.jpg')])
            ->assertCreated()
            ->assertJsonPath('pre_inscription.numero_dossier', 'ZRM000'.now()->year);
        $this->postJson('/api/v1/etudiant/pre-inscription', [...$payload, 'email' => 'marc2@example.com', 'photo_identite' => UploadedFile::fake()->image('identite2.jpg')])
            ->assertCreated()
            ->assertJsonPath('pre_inscription.numero_dossier', 'ZRM001'.now()->year);
    }

    public function test_l_eglise_et_la_civilite_doivent_exister(): void
    {
        $eglise = Eglise::query()->create([
            'code' => 'EGL-001', 'nom' => 'Église test', 'ville_commune' => 'Cocody',
        ]);
        $payload = [
            'nom' => 'Kouassi', 'prenoms' => 'Jean', 'email' => 'jean@example.com',
            'telephone' => '+2250102030405', 'civilite_id' => 999999,
            'photo_identite' => UploadedFile::fake()->image('identite.jpg'),
        ];

        $this->postJson('/api/v1/etudiant/pre-inscription', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['eglise_id', 'civilite_id']);

        $civilite = Civilite::query()->create(['code' => 'MME', 'name' => 'Madame']);
        $eglise->delete();
        $this->postJson('/api/v1/etudiant/pre-inscription', [...$payload, 'civilite_id' => $civilite->id, 'eglise_id' => $eglise->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['eglise_id'])
            ->assertJsonMissingValidationErrors(['civilite_id']);
    }

    public function test_l_email_doit_etre_unique(): void
    {
        $civilite = Civilite::query()->create(['code' => 'M', 'name' => 'Monsieur']);
        $eglise = Eglise::query()->create([
            'code' => 'EGL-001', 'nom' => 'Eglise test', 'ville_commune' => 'Cocody',
        ]);
        \DB::table('etudiants')->insert([
            'matricule' => 'EBAC-0001-'.now()->year,
            'nom' => 'Existant',
            'prenoms' => 'Etudiant',
            'email' => 'existant@example.com',
            'date_inscription' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/v1/etudiant/pre-inscription', [
            'nom' => 'Nouveau',
            'prenoms' => 'Etudiant',
            'email' => 'existant@example.com',
            'telephone' => '+2250700000000',
            'civilite_id' => $civilite->id,
            'eglise_id' => $eglise->id,
            'photo_identite' => UploadedFile::fake()->image('identite.jpg'),
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }

    public function test_le_courriel_de_confirmation_peut_etre_rendu(): void
    {
        $notification = new PreInscriptionRecueNotification(
            nomComplet: 'Marc Zran',
            numeroDossier: 'ZRM000'.now()->year,
        );

        $contenu = $notification->toMail(new \stdClass)->render();

        $this->assertStringContainsString('Demande de pré-inscription reçue', $contenu);
        $this->assertStringContainsString('en cours d’analyse', $contenu);
        $this->assertStringNotContainsString('Matricule', $contenu);
        $this->assertStringContainsString('ZRM000'.now()->year, $contenu);
        $this->assertStringContainsString('<img', $contenu);
        $this->assertStringContainsString('alt="Logo EBAC"', $contenu);
    }
}
