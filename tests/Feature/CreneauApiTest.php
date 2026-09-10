<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Cours;
use App\Models\Creneau;
use App\Models\Matiere;
use App\Models\Module;
use App\Models\ModuleCalendrier;
use App\Models\Niveau;
use App\Models\Role;
use App\Models\Salle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreneauApiTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/parametres/creneaux';

    private function contexte(): array
    {
        $role = Role::create(['code' => 'ADMIN', 'libelle' => 'Admin']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));
        $role = Role::create(['code' => 'ENSEIGNANT', 'libelle' => 'Enseignant']);
        $teacher = User::factory()->create(['id_role' => $role->id]);
        $annee = AnneeAcademique::create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-30']);
        $calendrier = $annee->calendrier()->create([]);
        $module = $calendrier->modules()->create(['libelle' => 'Module 1', 'ordre' => 1, 'date_debut' => '2026-09-01', 'date_fin' => '2026-12-20']);
        $niveau = Niveau::create(['code' => 'N1', 'libelle' => 'Niveau 1', 'rang' => 1]);
        $matiere = Matiere::create(['code' => 'MAT1', 'libelle' => 'Matière 1', 'id_niveau' => $niveau->id]);
        $salle = Salle::create(['nom' => 'Salle A', 'code' => 'A']);

        return ['id_module_calendrier' => $module->id, 'id_niveau' => $niveau->id, 'id_matiere' => $matiere->id,
            'enseignant_id' => $teacher->id, 'id_salle' => $salle->id, 'jour' => 1, 'heure_debut' => '08:00', 'heure_fin' => '10:00'];
    }

    public function test_crud_options_et_statistiques(): void
    {
        $data = $this->contexte();
        $id = $this->postJson(self::URL, $data)->assertCreated()->assertJsonPath('creneau.duree_minutes', 120)->json('creneau.id');
        $this->getJson(self::URL.'/'.$id)->assertOk()->assertJsonPath('creneau.heure_debut', '08:00')->assertJsonPath('creneau.cours', null);
        $this->getJson(self::URL.'?id_annee_academique=1')->assertOk()->assertJsonPath('nombre_creneaux', 1)->assertJsonPath('heures_hebdomadaires', 2);
        $this->getJson(self::URL.'/options?id_annee_academique=1')->assertOk()->assertJsonCount(1, 'modules_calendrier')->assertJsonCount(1, 'enseignants');
        $this->patchJson(self::URL.'/'.$id, ['heure_fin' => '09:30'])->assertOk()->assertJsonPath('creneau.duree_minutes', 90);
        $this->putJson(self::URL.'/'.$id, [...$data, 'jour' => 2])->assertOk();
        $this->getJson(self::URL.'?id_annee_academique=1&id_module_calendrier=999')->assertOk()->assertJsonCount(0, 'creneaux');
        $this->deleteJson(self::URL.'/'.$id)->assertOk();
        $this->assertSoftDeleted('creneaux', ['id' => $id]);
        $this->getJson(self::URL.'/'.$id)->assertNotFound();
        $this->postJson(self::URL, $data)->assertCreated();
    }

    public function test_trois_types_de_conflits_et_horaires_adjacents(): void
    {
        $data = $this->contexte();
        $this->postJson(self::URL, $data)->assertCreated();
        $niveau = Niveau::create(['code' => 'N2', 'libelle' => 'Niveau 2', 'rang' => 2]);
        $matiere = Matiere::create(['code' => 'MAT2', 'libelle' => 'Matière 2', 'id_niveau' => $niveau->id]);
        $teacher = User::factory()->create(['id_role' => Role::where('code', 'ENSEIGNANT')->first()->id]);
        $salle = Salle::create(['nom' => 'B', 'code' => 'B']);
        $autre = [...$data, 'id_niveau' => $niveau->id, 'id_matiere' => $matiere->id, 'enseignant_id' => $teacher->id, 'id_salle' => $salle->id];
        foreach (['enseignant_id', 'id_salle', 'id_niveau'] as $key) {
            $payload = [...$autre, $key => $data[$key], 'heure_debut' => '09:00'];
            if ($key === 'id_niveau') {
                $payload['id_matiere'] = $data['id_matiere'];
            }
            $this->postJson(self::URL, $payload)->assertUnprocessable()->assertJsonValidationErrors($key);
        }
        $this->postJson(self::URL, $autre)->assertCreated();
        $this->postJson(self::URL, [...$data, 'heure_debut' => '10:00', 'heure_fin' => '11:00'])->assertCreated();
        $this->postJson(self::URL, [...$data, 'heure_debut' => '07:00', 'heure_fin' => '08:00'])->assertCreated();
        $this->postJson(self::URL, [...$data, 'jour' => 2])->assertCreated();
    }

    public function test_validation_references_et_heures(): void
    {
        $data = $this->contexte();
        foreach ([['jour', 0], ['heure_debut', '25:00'], ['heure_fin', '08:00'], ['heure_fin', '07:00'], ['id_module_calendrier', 999], ['id_niveau', 999], ['id_matiere', 999], ['id_cours', 999], ['id_promotion', 999], ['enseignant_id', 999], ['id_salle', 999]] as [$key, $value]) {
            $this->postJson(self::URL, [...$data, $key => $value])->assertUnprocessable()->assertJsonValidationErrors($key);
        }
        Salle::find($data['id_salle'])->update(['statut' => 'Inactif']);
        $this->postJson(self::URL, $data)->assertUnprocessable()->assertJsonValidationErrors('id_salle');
        $this->assertDatabaseCount('creneaux', 0);
    }

    public function test_cours_facultatif_et_coherence_matiere(): void
    {
        $data = $this->contexte();
        $module = Module::create(['libelle' => 'Contenu', 'id_matiere' => $data['id_matiere']]);
        $cours = Cours::create(['libelle' => 'Cours', 'id_module' => $module->id]);
        $id = $this->postJson(self::URL, [...$data, 'id_cours' => $cours->id])->assertCreated()->json('creneau.id');
        $matiere = Matiere::create(['code' => 'AUTRE', 'libelle' => 'Autre', 'id_niveau' => $data['id_niveau']]);
        $this->patchJson(self::URL.'/'.$id, ['id_matiere' => $matiere->id])->assertUnprocessable()->assertJsonValidationErrors('id_cours');
        $this->patchJson(self::URL.'/'.$id, ['id_cours' => null])->assertOk()->assertJsonPath('creneau.cours', null);
    }

    public function test_modules_non_simultanes_et_protection_du_calendrier(): void
    {
        $data = $this->contexte();
        $id = $this->postJson(self::URL, $data)->assertCreated()->json('creneau.id');
        $module = ModuleCalendrier::first();
        $autre = $module->calendrier->modules()->create(['libelle' => 'Module 2', 'ordre' => 2, 'date_debut' => '2027-01-01', 'date_fin' => '2027-06-30']);
        $this->postJson(self::URL, [...$data, 'id_module_calendrier' => $autre->id])->assertCreated();
        $this->deleteJson('/api/v1/parametres/annees-academiques/1/calendrier')->assertUnprocessable();
        $this->getJson(self::URL.'/'.$id)->assertOk();
        $payload = ['modules' => [['libelle' => 'Remplacé', 'date_debut' => '2026-09-01', 'date_fin' => '2027-06-30', 'examens' => [], 'rattrapages' => []]], 'jours_feries' => [], 'conges' => [], 'grandes_vacances' => null];
        $this->putJson('/api/v1/parametres/annees-academiques/1/calendrier', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('modules_calendrier', 2);
    }

    public function test_patch_conflictuel_ne_modifie_rien(): void
    {
        $data = $this->contexte();
        $this->postJson(self::URL, $data)->assertCreated();
        $id = $this->postJson(self::URL, [...$data, 'jour' => 2])->assertCreated()->json('creneau.id');
        $this->patchJson(self::URL.'/'.$id, ['jour' => 1])->assertUnprocessable();
        $this->assertSame(2, Creneau::find($id)->jour);
        $this->patchJson(self::URL.'/'.$id, ['heure_debut' => '11:00'])->assertUnprocessable()->assertJsonValidationErrors('heure_fin');
    }

    public function test_conflits_entre_annees_et_absence_de_jour_commun(): void
    {
        $data = $this->contexte();
        $this->postJson(self::URL, $data)->assertCreated();
        $annee = AnneeAcademique::create(['libelle' => 'Autre année', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-30']);
        $calendrier = $annee->calendrier()->create([]);
        $module = $calendrier->modules()->create(['libelle' => 'Module', 'ordre' => 1, 'date_debut' => '2026-09-01', 'date_fin' => '2026-12-20']);
        $this->postJson(self::URL, [...$data, 'id_module_calendrier' => $module->id])->assertUnprocessable()->assertJsonValidationErrors('enseignant_id');
        // Le 1er septembre 2026 est un mardi : aucun lundi commun.
        $module->update(['date_fin' => '2026-09-01']);
        $this->postJson(self::URL, [...$data, 'id_module_calendrier' => $module->id])->assertCreated();
    }

    public function test_calendrier_remplacable_apres_suppression_des_creneaux(): void
    {
        $data = $this->contexte();
        $id = $this->postJson(self::URL, $data)->assertCreated()->json('creneau.id');
        $this->deleteJson(self::URL.'/'.$id)->assertOk();
        $payload = ['modules' => [['libelle' => 'Nouveau', 'date_debut' => '2026-09-01', 'date_fin' => '2027-06-30', 'examens' => [], 'rattrapages' => []]], 'jours_feries' => [], 'conges' => [], 'grandes_vacances' => null];
        $this->putJson('/api/v1/parametres/annees-academiques/1/calendrier', $payload)->assertOk();
        $this->assertNull(Creneau::withTrashed()->find($id)->id_module_calendrier);
    }

    public function test_authentification_et_inaccessibilite_annee_supprimee(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();
        $this->postJson(self::URL, [])->assertUnauthorized();
        $this->patchJson(self::URL.'/1', [])->assertUnauthorized();
        $this->deleteJson(self::URL.'/1')->assertUnauthorized();
        $data = $this->contexte();
        $id = $this->postJson(self::URL, $data)->assertCreated()->json('creneau.id');
        AnneeAcademique::first()->delete();
        $this->getJson(self::URL.'/'.$id)->assertNotFound();
        $this->postJson(self::URL, $data)->assertUnprocessable();
        $this->getJson(self::URL.'?id_annee_academique=1')->assertNotFound();
    }
}
