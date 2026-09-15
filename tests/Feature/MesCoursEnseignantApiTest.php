<?php

namespace Tests\Feature;

use App\Models\AffectationEnseignant;
use App\Models\AnneeAcademique;
use App\Models\Cours;
use App\Models\Matiere;
use App\Models\Module;
use App\Models\Niveau;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MesCoursEnseignantApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_un_enseignant_ne_voit_que_ses_affectations_et_le_detail_des_cours(): void
    {
        Carbon::setTestNow('2026-09-14 12:00:00');
        $role = Role::create(['code' => 'ENSEIGNANT', 'libelle' => 'Enseignant']);
        $enseignant = User::factory()->create(['id_role' => $role->id]);
        $autre = User::factory()->create(['id_role' => $role->id]);
        $annee = AnneeAcademique::create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'active' => true]);
        $niveau = Niveau::create(['code' => 'N1', 'libelle' => '1ère Année', 'rang' => 1]);
        $matiere = Matiere::create(['code' => 'MAT-TSI-A1', 'libelle' => 'Théologie Systématique I', 'id_niveau' => $niveau->id]);
        $module = Module::create(['id_matiere' => $matiere->id, 'libelle' => 'Doctrine de Dieu', 'ordre' => 1]);
        $cours1 = Cours::create(['id_module' => $module->id, 'code' => 'C-1', 'libelle' => 'Les attributs de Dieu', 'ordre' => 1]);
        Cours::create(['id_module' => $module->id, 'code' => 'C-2', 'libelle' => 'La Trinité', 'ordre' => 2]);
        AffectationEnseignant::create(['enseignant_id' => $enseignant->id, 'id_matiere' => $matiere->id, 'id_cours' => $cours1->id, 'portee' => 'cours', 'date_debut' => '2026-09-01']);
        AffectationEnseignant::create(['enseignant_id' => $autre->id, 'id_matiere' => $matiere->id, 'portee' => 'matiere', 'date_debut' => '2026-09-01']);

        Sanctum::actingAs($enseignant);
        $this->getJson('/api/v1/enseignant/mes-cours')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('matieres.0.nombre_cours', 1)
            ->assertJsonPath('matieres.0.libelle', 'Théologie Systématique I');
        $this->getJson('/api/v1/enseignant/mes-cours/'.$matiere->id)->assertOk()
            ->assertJsonCount(1, 'matiere.modules.0.cours')->assertJsonPath('matiere.modules.0.cours.0.id', $cours1->id);
    }

    public function test_endpoint_reserve_au_role_enseignant(): void
    {
        $role = Role::create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));
        $this->getJson('/api/v1/enseignant/mes-cours')->assertForbidden();
    }
}
