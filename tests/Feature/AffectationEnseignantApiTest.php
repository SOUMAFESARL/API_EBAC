<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Cours;
use App\Models\Matiere;
use App\Models\Module;
use App\Models\Niveau;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AffectationEnseignantApiTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/administration/affectations-enseignants';

    private function contexte(): array
    {
        $role = Role::firstOrCreate(['code' => 'ENSEIGNANT'], ['libelle' => 'Enseignant']);
        $teacher = User::factory()->create(['id_role' => $role->id, 'nom' => 'KONAN', 'prenoms' => 'Abraham']);
        $year = AnneeAcademique::create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31']);
        $level = Niveau::create(['code' => 'N3', 'libelle' => '3e Année', 'rang' => 3]);
        $subject = Matiere::create(['code' => 'MAT-HB-A2', 'libelle' => 'Herméneutique Biblique', 'id_niveau' => $level->id]);
        $module = Module::create(['id_matiere' => $subject->id, 'libelle' => 'Homilétique', 'ordre' => 1]);
        $course = Cours::create(['id_module' => $module->id, 'code' => 'COURS-1', 'libelle' => 'Introduction', 'ordre' => 1]);
        return compact('teacher', 'year', 'subject', 'course');
    }

    private function connecter(string $code = 'ADMIN'): void
    {
        $role = Role::firstOrCreate(['code' => $code], ['libelle' => $code]);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));
    }

    public function test_affecter_lister_terminer_et_conserver_historique(): void
    {
        $data = $this->contexte();
        $this->connecter();
        $payload = ['id_annee_academique' => $data['year']->id, 'enseignant_id' => $data['teacher']->id,
            'portee' => 'matiere', 'id_matiere' => $data['subject']->id, 'date_debut' => '2026-09-12'];
        $id = $this->postJson(self::URL, $payload)->assertCreated()->assertJsonPath('affectation.statut', 'en_cours')->json('affectation.id');
        $this->assertDatabaseHas('matieres', ['id' => $data['subject']->id, 'enseignant_id' => $data['teacher']->id]);
        $this->postJson(self::URL, $payload)->assertUnprocessable()->assertJsonValidationErrors('id_matiere');
        $this->getJson(self::URL.'?id_annee_academique='.$data['year']->id)->assertOk()->assertJsonCount(1, 'affectations');
        $this->getJson(self::URL.'/'.$id)->assertOk()->assertJsonPath('affectation.portee', 'matiere');
        $this->patchJson(self::URL.'/'.$id.'/terminer', ['date_fin' => '2026-09-12', 'motif' => 'Changement d’intervenant'])->assertOk()->assertJsonPath('affectation.statut', 'terminee');
        $this->assertDatabaseHas('affectations_enseignants', ['id' => $id, 'motif_fin' => 'Changement d’intervenant']);
        $this->assertDatabaseHas('matieres', ['id' => $data['subject']->id, 'enseignant_id' => null]);
    }

    public function test_portee_cours_options_et_tableau_de_bord(): void
    {
        $data = $this->contexte();
        $this->connecter('DIRECTION');
        $this->getJson(self::URL.'/options?id_annee_academique='.$data['year']->id)->assertOk()->assertJsonCount(1, 'enseignants')->assertJsonCount(1, 'cours');
        $payload = ['id_annee_academique' => $data['year']->id, 'enseignant_id' => $data['teacher']->id, 'portee' => 'cours',
            'id_matiere' => $data['subject']->id, 'id_cours' => $data['course']->id, 'date_debut' => '2026-09-12'];
        $this->postJson(self::URL, $payload)->assertCreated()->assertJsonPath('affectation.cours.id', $data['course']->id);
        $this->getJson(self::URL.'/tableau-de-bord?id_annee_academique='.$data['year']->id)->assertOk()
            ->assertJsonPath('indicateurs.enseignements_confies', 1)->assertJsonPath('indicateurs.enseignants_mobilises', 1)->assertJsonPath('indicateurs.cours_sans_enseignant', 0);
    }

    public function test_controle_des_roles(): void
    {
        $data = $this->contexte();
        foreach (['ADMIN', 'SECRETAIRE_ACADEMIQUE', 'DIRECTION', 'GESTIONNAIRE'] as $role) {
            $this->connecter($role);
            $this->getJson(self::URL.'/tableau-de-bord?id_annee_academique='.$data['year']->id)->assertOk();
        }
        foreach (['SECRETARIAT', 'ENSEIGNANT', 'ETUDIANT'] as $role) {
            $this->connecter($role);
            $this->getJson(self::URL.'/tableau-de-bord?id_annee_academique='.$data['year']->id)->assertForbidden();
        }
    }

    public function test_refuse_enseignant_inactif_dates_invalides_et_cours_d_une_autre_matiere(): void
    {
        $data = $this->contexte();
        $this->connecter();
        $base = ['id_annee_academique' => $data['year']->id, 'enseignant_id' => $data['teacher']->id,
            'portee' => 'cours', 'id_matiere' => $data['subject']->id, 'id_cours' => $data['course']->id, 'date_debut' => '2026-09-12'];

        $data['teacher']->update(['statut' => 'Suspendu']);
        $this->postJson(self::URL, $base)->assertUnprocessable()->assertJsonValidationErrors('enseignant_id');
        $data['teacher']->update(['statut' => 'Actif']);
        $this->postJson(self::URL, [...$base, 'date_debut' => '2026-08-31'])->assertUnprocessable()->assertJsonValidationErrors('date_debut');

        $autre = Matiere::create(['code' => 'MAT-2', 'libelle' => 'Autre matière', 'id_niveau' => $data['subject']->id_niveau]);
        $module = Module::create(['id_matiere' => $autre->id, 'libelle' => 'Autre module', 'ordre' => 1]);
        $cours = Cours::create(['id_module' => $module->id, 'code' => 'COURS-2', 'libelle' => 'Autre cours', 'ordre' => 1]);
        $this->postJson(self::URL, [...$base, 'id_cours' => $cours->id])->assertUnprocessable()->assertJsonValidationErrors('id_cours');
        $this->assertDatabaseCount('affectations_enseignants', 0);
    }

    public function test_refuse_les_doubles_couvertures_matiere_et_cours_dans_les_deux_sens(): void
    {
        $data = $this->contexte();
        $this->connecter();
        $base = ['id_annee_academique' => $data['year']->id, 'enseignant_id' => $data['teacher']->id,
            'id_matiere' => $data['subject']->id, 'date_debut' => '2026-09-12'];
        $this->postJson(self::URL, [...$base, 'portee' => 'cours', 'id_cours' => $data['course']->id])->assertCreated();
        $this->postJson(self::URL, [...$base, 'portee' => 'matiere'])->assertUnprocessable()->assertJsonValidationErrors('id_matiere');

        $this->patchJson(self::URL.'/1/terminer', ['date_fin' => '2026-09-12'])->assertOk();
        $this->postJson(self::URL, [...$base, 'portee' => 'matiere'])->assertCreated();
        $this->postJson(self::URL, [...$base, 'portee' => 'cours', 'id_cours' => $data['course']->id])->assertUnprocessable()->assertJsonValidationErrors('id_matiere');
    }

    public function test_terminaison_invalide_ou_repetee_est_refusee(): void
    {
        $data = $this->contexte();
        $this->connecter();
        $payload = ['id_annee_academique' => $data['year']->id, 'enseignant_id' => $data['teacher']->id,
            'portee' => 'matiere', 'id_matiere' => $data['subject']->id, 'date_debut' => '2026-09-12'];
        $id = $this->postJson(self::URL, $payload)->assertCreated()->json('affectation.id');
        $this->patchJson(self::URL.'/'.$id.'/terminer', ['date_fin' => '2026-09-11'])->assertUnprocessable()->assertJsonValidationErrors('date_fin');
        $this->patchJson(self::URL.'/'.$id.'/terminer', ['date_fin' => '2027-08-01'])->assertUnprocessable()->assertJsonValidationErrors('date_fin');
        $this->patchJson(self::URL.'/'.$id.'/terminer', ['date_fin' => '2026-09-12'])->assertOk();
        $this->patchJson(self::URL.'/'.$id.'/terminer', ['date_fin' => '2026-09-13'])->assertUnprocessable()->assertJsonValidationErrors('date_fin');
    }

    public function test_validation_des_champs_conditionnels_de_portee(): void
    {
        $data = $this->contexte();
        $this->connecter();
        $base = ['id_annee_academique' => $data['year']->id, 'enseignant_id' => $data['teacher']->id,
            'id_matiere' => $data['subject']->id, 'date_debut' => '2026-09-12'];
        $this->postJson(self::URL, [...$base, 'portee' => 'cours'])->assertUnprocessable()->assertJsonValidationErrors('id_cours');
        $this->postJson(self::URL, [...$base, 'portee' => 'matiere', 'id_cours' => $data['course']->id])->assertUnprocessable()->assertJsonValidationErrors('id_cours');
        $this->postJson(self::URL, [...$base, 'portee' => 'inconnue'])->assertUnprocessable()->assertJsonValidationErrors('portee');
    }
}
