<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Cours;
use App\Models\Creneau;
use App\Models\Matiere;
use App\Models\Module;
use App\Models\Niveau;
use App\Models\Promotion;
use App\Models\PublicationProgramme;
use App\Models\Role;
use App\Models\Salle;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmploiDuTempsEnseignantApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_enseignant_voit_uniquement_ses_creneaux_groupes_par_jour(): void
    {
        Carbon::setTestNow('2026-09-14 12:00:00');
        $role = Role::create(['code' => 'ENSEIGNANT', 'libelle' => 'Enseignant']);
        $enseignant = User::factory()->create(['id_role' => $role->id]);
        $autre = User::factory()->create(['id_role' => $role->id]);
        $annee = AnneeAcademique::create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'active' => true]);
        $calendrier = $annee->calendrier()->create([]);
        $moduleCalendrier = $calendrier->modules()->create(['libelle' => 'Module 1', 'ordre' => 1, 'date_debut' => '2026-09-01', 'date_fin' => '2026-12-20']);
        $niveau = Niveau::create(['code' => 'N1', 'libelle' => '1ère Année', 'rang' => 1]);
        $promotion = Promotion::create(['num_promotion' => 4, 'annee_entree' => 2026, 'id_niveau' => $niveau->id]);
        $matiere = Matiere::create(['code' => 'MAT-TSI-A1', 'libelle' => 'Théologie Systématique I', 'id_niveau' => $niveau->id]);
        $module = Module::create(['id_matiere' => $matiere->id, 'libelle' => 'Doctrine de Dieu', 'ordre' => 1]);
        $cours = Cours::create(['id_module' => $module->id, 'code' => 'C-1', 'libelle' => 'Les attributs de Dieu', 'ordre' => 1]);
        $salle = Salle::create(['nom' => 'Amphithéâtre A', 'code' => 'AMP-A']);
        $base = ['id_module_calendrier' => $moduleCalendrier->id, 'id_niveau' => $niveau->id, 'id_matiere' => $matiere->id,
            'id_cours' => $cours->id, 'id_promotion' => $promotion->id, 'id_salle' => $salle->id, 'jour' => 1, 'heure_debut' => '08:00:00', 'heure_fin' => '10:00:00'];
        Creneau::create([...$base, 'enseignant_id' => $enseignant->id]);
        Creneau::create([...$base, 'enseignant_id' => $autre->id, 'jour' => 2]);
        PublicationProgramme::create(['id_calendrier' => $calendrier->id, 'statut' => 'publie', 'version' => 1, 'date_publication' => now()]);

        Sanctum::actingAs($enseignant);
        $this->getJson('/api/v1/enseignant/emploi-du-temps')->assertOk()
            ->assertJsonPath('planning_hebdomadaire', true)
            ->assertJsonPath('nombre_creneaux', 1)
            ->assertJsonPath('heures_hebdomadaires', 2)
            ->assertJsonPath('jours.0.libelle', 'Lundi')
            ->assertJsonPath('jours.0.creneaux.0.cours.libelle', 'Les attributs de Dieu')
            ->assertJsonPath('jours.0.creneaux.0.salle.nom', 'Amphithéâtre A');
    }

    public function test_endpoint_est_reserve_aux_enseignants(): void
    {
        $role = Role::create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));
        $this->getJson('/api/v1/enseignant/emploi-du-temps')->assertForbidden();
    }

    public function test_endpoint_exige_une_authentification_et_valide_les_filtres(): void
    {
        $this->getJson('/api/v1/enseignant/emploi-du-temps')->assertUnauthorized();

        $role = Role::create(['code' => 'ENSEIGNANT', 'libelle' => 'Enseignant']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));
        $this->getJson('/api/v1/enseignant/emploi-du-temps?id_annee_academique=999')
            ->assertUnprocessable()->assertJsonValidationErrors('id_annee_academique');
        $this->getJson('/api/v1/enseignant/emploi-du-temps?id_module_calendrier=999')
            ->assertUnprocessable()->assertJsonValidationErrors('id_module_calendrier');
    }

    public function test_selectionne_le_module_courant_puis_permet_un_module_explicite(): void
    {
        Carbon::setTestNow('2026-09-14 12:00:00');
        $role = Role::create(['code' => 'ENSEIGNANT', 'libelle' => 'Enseignant']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));
        $annee = AnneeAcademique::create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'active' => true]);
        $calendrier = $annee->calendrier()->create([]);
        $courant = $calendrier->modules()->create(['libelle' => 'Module courant', 'ordre' => 1, 'date_debut' => '2026-09-01', 'date_fin' => '2026-12-20']);
        $suivant = $calendrier->modules()->create(['libelle' => 'Module suivant', 'ordre' => 2, 'date_debut' => '2027-01-01', 'date_fin' => '2027-03-31']);
        PublicationProgramme::create(['id_calendrier' => $calendrier->id, 'statut' => 'publie', 'version' => 1, 'date_publication' => now()]);

        $this->getJson('/api/v1/enseignant/emploi-du-temps')->assertOk()
            ->assertJsonPath('module_calendrier.id', $courant->id)
            ->assertJsonCount(2, 'modules_disponibles');
        $this->getJson('/api/v1/enseignant/emploi-du-temps?id_module_calendrier='.$suivant->id)->assertOk()
            ->assertJsonPath('module_calendrier.id', $suivant->id);
    }

    public function test_refuse_un_module_appartenant_a_une_autre_annee(): void
    {
        $role = Role::create(['code' => 'ENSEIGNANT', 'libelle' => 'Enseignant']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));
        $annee = AnneeAcademique::create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'active' => true]);
        $annee->calendrier()->create([])->modules()->create(['libelle' => 'Module 1', 'ordre' => 1, 'date_debut' => '2026-09-01', 'date_fin' => '2026-12-20']);
        $autreAnnee = AnneeAcademique::create(['libelle' => '2027-2028', 'date_debut' => '2027-09-01', 'date_fin' => '2028-07-31']);
        $autreModule = $autreAnnee->calendrier()->create([])->modules()->create(['libelle' => 'Autre module', 'ordre' => 1, 'date_debut' => '2027-09-01', 'date_fin' => '2027-12-20']);

        $this->getJson('/api/v1/enseignant/emploi-du-temps?id_annee_academique='.$annee->id.'&id_module_calendrier='.$autreModule->id)
            ->assertUnprocessable();
    }
}
