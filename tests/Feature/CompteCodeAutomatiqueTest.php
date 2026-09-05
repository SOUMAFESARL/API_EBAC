<?php

namespace Tests\Feature;

use App\Models\Civilite;
use App\Models\Etudiant;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Notifications\CompteCreeNotification;
use App\Notifications\CompteEtudiantCreeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompteCodeAutomatiqueTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_code_du_compte_est_genere_automatiquement(): void
    {
        Notification::fake();
        $role = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id, 'code' => 'USR-ADMIN']));
        $civilite = Civilite::query()->create(['code' => 'M', 'name' => 'Monsieur']);

        $this->postJson('/api/v1/administration/comptes', [
            'civilite_id' => $civilite->id,
            'nom' => 'KOFFI',
            'prenoms' => 'Jean',
            'email' => 'jean.koffi@example.net',
            'id_role' => $role->id,
        ])->assertCreated()
            ->assertJsonPath('compte.code', 'USR-000001')
            ->assertJsonPath('compte.matricule', 'EBAC-0001-'.now()->year)
            ->assertJsonPath('compte.user_code', 'USR-ADMIN')
            ->assertJsonPath('compte.user_id', '1')
            ->assertJsonPath('compte.created_by', 1);
    }

    public function test_le_compte_eglise_recoit_son_mot_de_passe_temporaire_par_email(): void
    {
        Notification::fake();

        $roleAdministrateur = Role::query()->create([
            'code' => 'ADMIN',
            'libelle' => 'Administrateur',
        ]);
        $roleEglise = Role::query()->create([
            'code' => 'EGLISE',
            'libelle' => 'Église',
        ]);
        $administrateur = User::factory()->create([
            'id_role' => $roleAdministrateur->id,
            'code' => 'USR-ADMIN',
        ]);
        $civilite = Civilite::query()->create([
            'code' => 'EGL',
            'name' => 'Église',
        ]);
        Sanctum::actingAs($administrateur);

        $compteId = $this->postJson('/api/v1/administration/comptes', [
            'civilite_id' => $civilite->id,
            'nom' => 'Église Cité de la Grâce',
            'prenoms' => 'Compte',
            'email' => 'contact.eglise@example.net',
            'id_role' => $roleEglise->id,
        ])->assertCreated()
            ->assertJsonPath('message', 'Compte créé avec succès. Le mot de passe temporaire a été envoyé par email.')
            ->assertJsonPath('compte.email', 'contact.eglise@example.net')
            ->assertJsonPath('compte.role.code', 'EGLISE')
            ->json('compte.id');

        $compteEglise = User::query()->findOrFail($compteId);

        Notification::assertSentTo(
            $compteEglise,
            CompteCreeNotification::class,
            function (CompteCreeNotification $notification) use ($compteEglise): bool {
                $message = $notification->toMail($compteEglise);
                $motDePasseEnvoye = $message->viewData['motDePasseTemporaire'] ?? null;

                return is_string($motDePasseEnvoye)
                    && strlen($motDePasseEnvoye) === 16
                    && Hash::check($motDePasseEnvoye, $compteEglise->password);
            },
        );
    }

    public function test_un_secretaire_academique_peut_creer_un_compte_etudiant_et_le_mot_de_passe_est_envoye(): void
    {
        Notification::fake();

        $roleSecretaire = Role::query()->create([
            'code' => 'SECRETARIAT',
            'libelle' => 'Secrétaire académique',
        ]);
        $roleEtudiant = Role::query()->create([
            'code' => 'ETUDIANT',
            'libelle' => 'Étudiant',
        ]);
        $permission = Permission::query()->create([
            'code' => 'COMPTE_GERER',
            'libelle' => 'Gérer les comptes',
        ]);
        $roleSecretaire->permissions()->attach($permission->id, ['actif' => true]);

        Sanctum::actingAs(User::factory()->create(['id_role' => $roleSecretaire->id]));
        $civilite = Civilite::query()->create(['code' => 'M', 'name' => 'Monsieur']);

        $compteId = $this->postJson('/api/v1/administration/comptes/etudiants', [
            'civilite_id' => $civilite->id,
            'nom' => 'KOUASSI',
            'prenoms' => 'Paul',
            'email' => 'paul.kouassi@example.net',
            'id_role' => $roleSecretaire->id,
        ])->assertCreated()
            ->assertJsonPath('compte.role.code', 'ETUDIANT')
            ->json('compte.id');

        $compte = User::query()->findOrFail($compteId);

        $this->assertDatabaseHas('etudiants', [
            'user_id' => $compte->id,
            'matricule' => $compte->matricule,
            'email' => $compte->email,
            'created_by' => $compte->created_by,
        ]);

        Notification::assertSentTo(
            $compte,
            CompteEtudiantCreeNotification::class,
            function (CompteEtudiantCreeNotification $notification) use ($compte): bool {
                $motDePasse = $notification->toMail($compte)->viewData['motDePasseTemporaire'] ?? null;

                return is_string($motDePasse)
                    && strlen($motDePasse) === 16
                    && Hash::check($motDePasse, $compte->password);
            },
        );
    }

    public function test_le_endpoint_generique_cree_aussi_la_fiche_pour_un_role_etudiant(): void
    {
        Notification::fake();
        $roleAdmin = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        $roleEtudiant = Role::query()->create(['code' => 'ETUDIANT', 'libelle' => 'Étudiant']);
        $admin = User::factory()->create(['id_role' => $roleAdmin->id]);
        Sanctum::actingAs($admin);
        $civilite = Civilite::query()->create(['code' => 'M', 'name' => 'Monsieur']);

        $compteId = $this->postJson('/api/v1/administration/comptes', [
            'civilite_id' => $civilite->id,
            'nom' => 'YAO',
            'prenoms' => 'Anne',
            'email' => 'anne.yao@example.net',
            'id_role' => $roleEtudiant->id,
        ])->assertCreated()->json('compte.id');

        $this->assertDatabaseHas('etudiants', [
            'user_id' => $compteId,
            'email' => 'anne.yao@example.net',
            'statut' => 'En formation',
        ]);
    }

    public function test_la_creation_du_compte_copie_le_matricule_dans_la_preinscription_existante(): void
    {
        Notification::fake();
        $roleAdmin = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        $roleEtudiant = Role::query()->create(['code' => 'ETUDIANT', 'libelle' => 'Étudiant']);
        $admin = User::factory()->create(['id_role' => $roleAdmin->id]);
        Sanctum::actingAs($admin);
        $civilite = Civilite::query()->create(['code' => 'M', 'name' => 'Monsieur']);
        $fiche = Etudiant::query()->create([
            'matricule' => null,
            'nom' => 'KOFFI',
            'prenoms' => 'Jean',
            'civilite_id' => $civilite->id,
            'email' => 'jean.koffi@example.net',
            'date_inscription' => now()->toDateString(),
            'statut' => 'Préinscrit',
        ]);

        $compteId = $this->postJson('/api/v1/administration/comptes', [
            'civilite_id' => $civilite->id,
            'nom' => 'KOFFI',
            'prenoms' => 'Jean',
            'email' => 'jean.koffi@example.net',
            'id_role' => $roleEtudiant->id,
        ])->assertCreated()->json('compte.id');

        $compte = User::query()->findOrFail($compteId);
        $this->assertDatabaseHas('etudiants', [
            'id' => $fiche->id,
            'user_id' => $compte->id,
            'matricule' => $compte->matricule,
        ]);
    }

    public function test_un_utilisateur_sans_permission_ne_peut_pas_creer_un_compte_etudiant(): void
    {
        $role = Role::query()->create(['code' => 'ENSEIGNANT', 'libelle' => 'Enseignant']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));

        $this->postJson('/api/v1/administration/comptes/etudiants', [])
            ->assertForbidden();
    }

    public function test_un_code_fourni_par_le_client_est_refuse(): void
    {
        $role = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));
        $civilite = Civilite::query()->create(['code' => 'M', 'name' => 'Monsieur']);

        $this->postJson('/api/v1/administration/comptes', [
            'code' => 'CODE-MANUEL',
            'civilite_id' => $civilite->id,
            'nom' => 'KOFFI',
            'prenoms' => 'Jean',
            'email' => 'jean.koffi@example.net',
            'id_role' => $role->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_la_civilite_est_obligatoire_lors_de_la_creation_du_compte(): void
    {
        $role = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));

        $this->postJson('/api/v1/administration/comptes', [
            'nom' => 'KOFFI',
            'prenoms' => 'Jean',
            'email' => 'jean.koffi@example.net',
            'id_role' => $role->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('civilite_id');
    }

    public function test_le_template_de_creation_de_compte_utilise_le_design_ebac(): void
    {
        $message = new class
        {
            public function embed(string $path): string
            {
                return 'cid:logo-ebac';
            }
        };

        $html = view('emails.compte-cree', [
            'nomComplet' => 'Severin Zran',
            'email' => 'severin.zran@soumafe.ci',
            'motDePasseTemporaire' => 'Temporaire123',
            'role' => 'Administrateur',
            'urlConnexion' => 'https://ebac.ci',
            'message' => $message,
        ])->render();

        $this->assertStringContainsString('cid:logo-ebac', $html);
        $this->assertStringContainsString('border-radius:50%', $html);
        $this->assertStringContainsString('Temporaire123', $html);
        $this->assertStringContainsString('https://ebac.ci', $html);
        $this->assertStringNotContainsString('127.0.0.1', $html);
        $this->assertStringNotContainsString("If you're having trouble", $html);
    }

    public function test_le_message_etudiant_est_different_du_message_utilisateur(): void
    {
        $message = new class
        {
            public function embed(string $path): string
            {
                return 'cid:logo-ebac';
            }
        };

        $donneesCommunes = [
            'nomComplet' => 'Severin Zran',
            'email' => 'severin.zran@soumafe.ci',
            'motDePasseTemporaire' => 'Temporaire123',
            'urlConnexion' => 'https://ebac.ci',
            'message' => $message,
        ];

        $htmlUtilisateur = view('emails.compte-cree', $donneesCommunes + [
            'role' => 'Administrateur',
        ])->render();
        $htmlEtudiant = view('emails.compte-etudiant-cree', $donneesCommunes + [
            'matricule' => 'EBAC-0001-2026',
            'anneeAcademique' => '2026-2027',
            'eglise' => 'Église Alliance de Test',
            'numeroDossier' => 'DOS-0001-2026',
            'statutDossier' => 'Incomplet',
        ])->render();

        $this->assertStringContainsString('Rôle attribué', $htmlUtilisateur);
        $this->assertStringNotContainsString('Votre préinscription a été validée', $htmlUtilisateur);
        $this->assertStringContainsString('Votre préinscription a été validée', $htmlEtudiant);
        $this->assertStringContainsString('Matricule étudiant', $htmlEtudiant);
        $this->assertStringContainsString('2026-2027', $htmlEtudiant);
        $this->assertStringContainsString('DOS-0001-2026', $htmlEtudiant);
        $this->assertStringContainsString('Incomplet', $htmlEtudiant);
        $this->assertNotSame($htmlUtilisateur, $htmlEtudiant);
    }

    public function test_la_photo_est_enregistree_et_accessible_par_son_url_api(): void
    {
        Notification::fake();
        Storage::fake('public');
        $role = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id, 'code' => 'USR-ADMIN']));
        $civilite = Civilite::query()->create(['code' => 'M', 'name' => 'Monsieur']);

        $reponse = $this->post('/api/v1/administration/comptes', [
            'civilite_id' => $civilite->id,
            'nom' => 'KOFFI',
            'prenoms' => 'Jean',
            'email' => 'photo.koffi@example.net',
            'id_role' => $role->id,
            'photo' => UploadedFile::fake()->image('portrait.jpg'),
        ]);

        $reponse->assertCreated()->assertJsonPath('compte.photo_url', route(
            'api.v1.utilisateurs.photo',
            ['compte' => $reponse->json('compte.id'), 'v' => hash('sha256', $reponse->json('compte.photo'))],
        ));

        Storage::disk('public')->assertExists($reponse->json('compte.photo'));
        $this->get($reponse->json('compte.photo_url'))->assertOk();
    }
}
