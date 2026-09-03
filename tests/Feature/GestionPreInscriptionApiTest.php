<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Civilite;
use App\Models\DossierEtudiant;
use App\Models\Eglise;
use App\Models\Etudiant;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Notifications\CompteEtudiantCreeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GestionPreInscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_secretariat_liste_et_valide_une_preinscription(): void
    {
        Notification::fake();
        $roleSecretaire = Role::query()->create(['code' => 'SECRETAIRE_ACADEMIQUE', 'libelle' => 'Secrétaire académique']);
        Role::query()->create(['code' => 'ETUDIANT', 'libelle' => 'Étudiant']);
        $permission = Permission::query()->create(['code' => 'COMPTE_GERER', 'libelle' => 'Gérer les comptes']);
        $roleSecretaire->permissions()->attach($permission->id, ['actif' => true]);
        $secretaire = User::factory()->create(['id_role' => $roleSecretaire->id, 'code' => 'USR-SECR']);
        Sanctum::actingAs($secretaire);

        $civilite = Civilite::query()->create(['code' => 'M', 'name' => 'Monsieur']);
        $anneeAcademique = AnneeAcademique::query()->create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'active' => true,
        ]);
        $eglise = Eglise::query()->create([
            'code' => 'EGL-TEST',
            'nom' => 'Église Alliance de Test',
            'ville_commune' => 'Abidjan',
        ]);
        $etudiant = Etudiant::query()->create([
            'matricule' => 'EBAC-0042-'.now()->year,
            'nom' => 'KOFFI',
            'prenoms' => 'Jean',
            'civilite_id' => $civilite->id,
            'email' => 'jean.koffi@example.net',
            'telephone' => '0102030405',
            'eglise_id' => $eglise->id,
            'date_inscription' => now()->toDateString(),
            'statut' => 'Préinscrit',
        ]);
        $dossier = DossierEtudiant::query()->create([
            'id_etudiant' => $etudiant->id,
            'numero_dossier' => 'KOJ042'.now()->year,
            'statut' => 'Incomplet',
            'date_ouverture' => now()->toDateString(),
        ]);

        $this->getJson('/api/v1/administration/preinscriptions')
            ->assertOk()
            ->assertJsonPath('data.0.id', $etudiant->id)
            ->assertJsonPath('data.0.dossier.numero_dossier', $dossier->numero_dossier);

        $this->getJson("/api/v1/administration/preinscriptions/{$etudiant->id}/creer-compte")
            ->assertOk()
            ->assertJsonPath('preinscription.id', $etudiant->id)
            ->assertJsonPath('role.code', 'ETUDIANT')
            ->assertJsonPath('valeurs_par_defaut.statut', 'Actif');

        $compteId = $this->postJson("/api/v1/administration/preinscriptions/{$etudiant->id}/creer-compte", [
            'id_role' => $roleSecretaire->id,
        ])
            ->assertOk()
            ->assertJsonPath('compte.role.code', 'ETUDIANT')
            ->json('compte.id');

        $this->assertDatabaseHas('etudiants', ['id' => $etudiant->id, 'user_id' => $compteId, 'statut' => 'Inscrit']);
        $this->assertDatabaseHas('dossiers_etudiants', ['id' => $dossier->id, 'user_id' => $compteId, 'statut' => 'Validé']);

        $compte = User::query()->findOrFail($compteId);
        Notification::assertSentTo($compte, CompteEtudiantCreeNotification::class, function ($notification) use ($compte, $etudiant, $anneeAcademique, $eglise, $dossier): bool {
            $donneesEmail = $notification->toMail($compte)->viewData;
            $motDePasse = $donneesEmail['motDePasseTemporaire'] ?? null;

            return is_string($motDePasse)
                && Hash::check($motDePasse, $compte->password)
                && $donneesEmail['matricule'] === $etudiant->matricule
                && $donneesEmail['anneeAcademique'] === $anneeAcademique->libelle
                && $donneesEmail['eglise'] === $eglise->nom
                && $donneesEmail['numeroDossier'] === $dossier->numero_dossier
                && $donneesEmail['statutDossier'] === 'Complet';
        });
    }

    public function test_une_preinscription_ne_peut_pas_etre_validee_deux_fois(): void
    {
        Notification::fake();
        $adminRole = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        Role::query()->create(['code' => 'ETUDIANT', 'libelle' => 'Étudiant']);
        AnneeAcademique::query()->create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'active' => true,
        ]);
        Sanctum::actingAs(User::factory()->create(['id_role' => $adminRole->id]));
        $etudiant = Etudiant::query()->create([
            'matricule' => null,
            'nom' => 'YAO',
            'prenoms' => 'Anne',
            'email' => 'anne.yao@example.net',
            'telephone' => '0102030405',
            'date_inscription' => now()->toDateString(),
            'statut' => 'Préinscrit',
        ]);

        $url = "/api/v1/administration/preinscriptions/{$etudiant->id}/creer-compte";
        $this->postJson($url)
            ->assertOk()
            ->assertJsonPath('compte.matricule', 'EBAC-0001-'.now()->year);
        $this->postJson($url)->assertUnprocessable();
        $this->assertSame(1, User::query()->where('email', $etudiant->email)->count());
    }

    public function test_un_autre_role_sans_permission_peut_acceder_aux_preinscriptions(): void
    {
        $role = Role::query()->create(['code' => 'GESTIONNAIRE', 'libelle' => 'Gestionnaire']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));

        $this->getJson('/api/v1/administration/preinscriptions')->assertOk();
        $this->getJson('/api/v1/administration/preinscriptions/999999')->assertNotFound();
        $this->postJson('/api/v1/administration/preinscriptions/999999/creer-compte')->assertNotFound();
    }

    public function test_les_roles_enseignant_et_etudiant_ne_peuvent_pas_acceder_aux_preinscriptions(): void
    {
        foreach (['ENSEIGNANT', 'ETUDIANT'] as $codeRole) {
            $role = Role::query()->create(['code' => $codeRole, 'libelle' => $codeRole]);
            Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));

            $this->getJson('/api/v1/administration/preinscriptions')->assertForbidden();
            $this->getJson('/api/v1/administration/preinscriptions/1')->assertForbidden();
            $this->getJson('/api/v1/administration/preinscriptions/1/creer-compte')->assertForbidden();
            $this->postJson('/api/v1/administration/preinscriptions/1/creer-compte')->assertForbidden();
            $this->postJson('/api/v1/administration/preinscriptions/1/rejeter', ['motif' => 'Test'])->assertForbidden();
        }
    }

    public function test_un_gestionnaire_peut_rejeter_une_preinscription_avec_un_motif(): void
    {
        $role = Role::query()->create(['code' => 'GESTIONNAIRE', 'libelle' => 'Gestionnaire']);
        $gestionnaire = User::factory()->create(['id_role' => $role->id]);
        Sanctum::actingAs($gestionnaire);
        $etudiant = Etudiant::query()->create([
            'matricule' => 'EBAC-0100-'.now()->year,
            'nom' => 'YAO',
            'prenoms' => 'Marc',
            'email' => 'marc.yao@example.net',
            'telephone' => '0102030405',
            'date_inscription' => now()->toDateString(),
            'statut' => 'Préinscrit',
        ]);
        $dossier = DossierEtudiant::query()->create([
            'id_etudiant' => $etudiant->id,
            'numero_dossier' => 'YAM100'.now()->year,
            'statut' => 'Incomplet',
            'date_ouverture' => now()->toDateString(),
        ]);

        $this->postJson("/api/v1/administration/preinscriptions/{$etudiant->id}/rejeter", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('motif');

        $this->postJson("/api/v1/administration/preinscriptions/{$etudiant->id}/rejeter", [
            'motif' => 'Pièce d’identité illisible.',
        ])->assertOk()
            ->assertJsonPath('preinscription.statut', 'Rejeté')
            ->assertJsonPath('preinscription.dossier.statut', 'Rejeté')
            ->assertJsonPath('preinscription.dossier.observations', 'Pièce d’identité illisible.');

        $this->assertDatabaseHas('etudiants', ['id' => $etudiant->id, 'statut' => 'Rejeté']);
        $this->assertDatabaseHas('dossiers_etudiants', [
            'id' => $dossier->id,
            'statut' => 'Rejeté',
            'observations' => 'Pièce d’identité illisible.',
        ]);

        $this->getJson('/api/v1/administration/preinscriptions')
            ->assertOk()
            ->assertJsonPath('data.0.statut', 'Rejeté');
    }

    public function test_un_matricule_deja_utilise_par_un_compte_est_regenere_automatiquement(): void
    {
        Notification::fake();
        $roleAdmin = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        Role::query()->create(['code' => 'ETUDIANT', 'libelle' => 'Étudiant']);
        AnneeAcademique::query()->create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'active' => true,
        ]);
        $administrateur = User::factory()->create(['id_role' => $roleAdmin->id, 'matricule' => 'EBAC-0002-'.now()->year]);
        Sanctum::actingAs($administrateur);
        $etudiant = Etudiant::query()->create([
            'matricule' => 'EBAC-0002-'.now()->year,
            'nom' => 'ZRAN',
            'prenoms' => 'Severin',
            'email' => 'severin.unique@example.net',
            'telephone' => '0140004509',
            'date_inscription' => now()->toDateString(),
            'statut' => 'Préinscrit',
        ]);

        $compteId = $this->postJson("/api/v1/administration/preinscriptions/{$etudiant->id}/creer-compte")
            ->assertOk()
            ->assertJsonPath('compte.matricule', 'EBAC-0003-'.now()->year)
            ->json('compte.id');

        $this->assertDatabaseHas('users', [
            'id' => $compteId,
            'matricule' => 'EBAC-0003-'.now()->year,
        ]);
        $this->assertDatabaseHas('etudiants', [
            'id' => $etudiant->id,
            'matricule' => 'EBAC-0003-'.now()->year,
            'user_id' => $compteId,
        ]);
    }
}
