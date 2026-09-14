<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Creneau;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Role;
use App\Models\Salle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PublicationProgrammeApiTest extends TestCase
{
    use RefreshDatabase;

    private function contexte(): array
    {
        $adminRole = Role::create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        $enseignantRole = Role::create(['code' => 'ENSEIGNANT', 'libelle' => 'Enseignant']);
        $admin = User::factory()->create(['id_role' => $adminRole->id]);
        $enseignant = User::factory()->create(['id_role' => $enseignantRole->id]);
        $annee = AnneeAcademique::create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'active' => true]);
        $module = $annee->calendrier()->create([])->modules()->create(['libelle' => 'Module 1', 'ordre' => 1, 'date_debut' => '2026-09-01', 'date_fin' => '2026-12-20']);
        $niveau = Niveau::create(['code' => 'N1', 'libelle' => '1ère Année', 'rang' => 1]);
        $matiere = Matiere::create(['code' => 'MAT-1', 'libelle' => 'Matière 1', 'id_niveau' => $niveau->id]);
        $salle = Salle::create(['nom' => 'Salle A', 'code' => 'A']);
        $creneau = Creneau::create(['id_module_calendrier' => $module->id, 'id_niveau' => $niveau->id, 'id_matiere' => $matiere->id,
            'enseignant_id' => $enseignant->id, 'id_salle' => $salle->id, 'jour' => 1, 'heure_debut' => '08:00:00', 'heure_fin' => '10:00:00']);

        return compact('admin', 'enseignant', 'annee', 'module', 'creneau');
    }

    public function test_programme_non_publie_est_invisible_puis_devient_visible_apres_publication(): void
    {
        $data = $this->contexte();
        Sanctum::actingAs($data['enseignant']);
        $this->getJson('/api/v1/enseignant/emploi-du-temps')->assertOk()
            ->assertJsonPath('publication.statut', 'non_publie')->assertJsonPath('message', 'Le programme n’est pas encore disponible.')
            ->assertJsonPath('nombre_creneaux', 0)->assertJsonCount(0, 'jours');

        Sanctum::actingAs($data['admin']);
        $this->getJson('/api/v1/administration/publication-programme')->assertOk()
            ->assertJsonPath('programmes.0.statut', 'non_publie')->assertJsonPath('programmes.0.visible_utilisateurs', false);
        $this->postJson('/api/v1/administration/publication-programme/'.$data['module']->id.'/publier')->assertOk()
            ->assertJsonPath('programme.statut', 'publie')->assertJsonPath('programme.version', 1);

        Sanctum::actingAs($data['enseignant']);
        $this->getJson('/api/v1/enseignant/emploi-du-temps')->assertOk()
            ->assertJsonPath('publication.statut', 'publie')->assertJsonPath('nombre_creneaux', 1);
        $this->getJson('/api/v1/programme')->assertOk()
            ->assertJsonPath('publication.statut', 'publie')->assertJsonPath('nombre_creneaux', 1);
    }

    public function test_retrait_cache_immediatement_le_programme_et_republication_incremente_la_version(): void
    {
        $data = $this->contexte();
        Sanctum::actingAs($data['admin']);
        $url = '/api/v1/administration/publication-programme/'.$data['module']->id;
        $this->postJson($url.'/publier')->assertOk()->assertJsonPath('programme.version', 1);
        $this->postJson($url.'/retirer')->assertOk()->assertJsonPath('programme.visible_utilisateurs', false);
        $this->postJson($url.'/publier')->assertOk()->assertJsonPath('programme.version', 2);
    }

    public function test_modification_du_planning_retire_automatiquement_la_publication(): void
    {
        $data = $this->contexte();
        Sanctum::actingAs($data['admin']);
        $this->postJson('/api/v1/administration/publication-programme/'.$data['module']->id.'/publier')->assertOk();
        $this->patchJson('/api/v1/parametres/creneaux/'.$data['creneau']->id, ['heure_fin' => '11:00'])->assertOk();
        $this->assertDatabaseHas('publication_programmes', ['id_module_calendrier' => $data['module']->id, 'statut' => 'non_publie', 'retire_par' => $data['admin']->id]);
    }

    public function test_publication_exige_un_creneau_et_le_role_admin(): void
    {
        $data = $this->contexte();
        Sanctum::actingAs($data['enseignant']);
        $this->getJson('/api/v1/administration/publication-programme')->assertForbidden();
        $data['creneau']->delete();
        Sanctum::actingAs($data['admin']);
        $this->postJson('/api/v1/administration/publication-programme/'.$data['module']->id.'/publier')
            ->assertUnprocessable()->assertJsonValidationErrors('module');
    }

    public function test_programme_non_publie_est_cache_pour_tous_les_roles_utilisateurs(): void
    {
        $data = $this->contexte();
        foreach (['SECRETAIRE_ACADEMIQUE', 'GESTIONNAIRE', 'ETUDIANT'] as $code) {
            $role = Role::create(['code' => $code, 'libelle' => $code]);
            Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));
            $this->getJson('/api/v1/programme')->assertOk()
                ->assertJsonPath('publication.statut', 'non_publie')
                ->assertJsonPath('message', 'Le programme n’est pas encore disponible.')
                ->assertJsonPath('nombre_creneaux', 0)->assertJsonCount(0, 'creneaux');
        }
        Sanctum::actingAs($data['enseignant']);
        $this->getJson('/api/v1/programme')->assertOk()
            ->assertJsonPath('publication.statut', 'non_publie')->assertJsonPath('nombre_creneaux', 0);
    }
}
