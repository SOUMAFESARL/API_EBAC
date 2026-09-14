<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Cours;
use App\Models\Creneau;
use App\Models\Matiere;
use App\Models\Module;
use App\Models\Niveau;
use App\Models\Promotion;
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
}
