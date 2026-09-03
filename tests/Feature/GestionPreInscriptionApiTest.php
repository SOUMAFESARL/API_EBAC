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
use App\Notifications\CompteCreeNotification;
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

        $compteId = $this->postJson("/api/v1/administration/preinscriptions/{$etudiant->id}/creer-compte", [
            'id_role' => $roleSecretaire->id,
        ])
            ->assertOk()
            ->assertJsonPath('compte.role.code', 'ETUDIANT')
            ->json('compte.id');

        $this->assertDatabaseHas('etudiants', ['id' => $etudiant->id, 'user_id' => $compteId, 'statut' => 'Inscrit']);
        $this->assertDatabaseHas('dossiers_etudiants', ['id' => $dossier->id, 'user_id' => $compteId, 'statut' => 'Validé']);

        $compte = User::query()->findOrFail($compteId);
        Notification::assertSentTo($compte, CompteCreeNotification::class, function ($notification) use ($compte, $etudiant, $anneeAcademique, $eglise): bool {
            $donneesEmail = $notification->toMail($compte)->viewData;
            $motDePasse = $donneesEmail['motDePasseTemporaire'] ?? null;

            return is_string($motDePasse)
                && Hash::check($motDePasse, $compte->password)
                && $donneesEmail['matricule'] === $etudiant->matricule
                && $donneesEmail['anneeAcademique'] === $anneeAcademique->libelle
                && $donneesEmail['eglise'] === $eglise->nom;
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
            'matricule' => 'EBAC-0099-'.now()->year,
            'nom' => 'YAO',
            'prenoms' => 'Anne',
            'email' => 'anne.yao@example.net',
            'telephone' => '0102030405',
            'date_inscription' => now()->toDateString(),
            'statut' => 'Préinscrit',
        ]);

        $url = "/api/v1/administration/preinscriptions/{$etudiant->id}/creer-compte";
        $this->postJson($url)->assertOk();
        $this->postJson($url)->assertUnprocessable();
        $this->assertSame(1, User::query()->where('email', $etudiant->email)->count());
    }
}
