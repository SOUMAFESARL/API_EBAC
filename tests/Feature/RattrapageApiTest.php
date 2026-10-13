<?php

namespace Tests\Feature;

use App\Models\CoursAFaire;
use App\Models\Cours;
use App\Models\Etudiant;
use App\Models\FeuillePresence;
use App\Models\Matiere;
use App\Models\Module;
use App\Models\Niveau;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\SeanceCahierTexte;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RattrapageApiTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/administration/rattrapages';

    private function contexte(string $role = 'ADMIN'): array
    {
        $adminRole = Role::create(['code' => $role, 'libelle' => $role]);
        $teacherRole = Role::create(['code' => 'ENSEIGNANT', 'libelle' => 'Enseignant']);
        $admin = User::factory()->create(['id_role' => $adminRole->id]);
        $enseignant = User::factory()->create(['id_role' => $teacherRole->id]);
        $etudiant = Etudiant::create(['matricule' => 'ETU-1', 'nom' => 'Koffi', 'prenoms' => 'Anne', 'date_inscription' => '2026-09-01']);
        $niveau = Niveau::create(['code' => 'N1', 'libelle' => 'Niveau 1', 'rang' => 1]);
        $matiere = Matiere::create(['code' => 'MAT-1', 'libelle' => 'Theologie', 'id_niveau' => $niveau->id]);
        $module = Module::create(['id_matiere' => $matiere->id, 'libelle' => 'Doctrine', 'ordre' => 1]);
        $cours = Cours::create(['id_module' => $module->id, 'code' => 'C1', 'libelle' => 'Conciles', 'ordre' => 1]);
        $seance = SeanceCahierTexte::create(['enseignant_id' => $enseignant->id, 'id_niveau' => $niveau->id,
            'id_matiere' => $matiere->id, 'id_cours' => $cours->id, 'date_prevue' => '2026-09-14', 'heure_debut_prevue' => '08:00', 'statut' => 'realisee']);
        $feuille = FeuillePresence::create(['id_seance' => $seance->id, 'statut' => 'validee']);
        $presence = $feuille->presences()->create(['id_etudiant' => $etudiant->id, 'statut' => 'absent']);
        $aFaire = CoursAFaire::create(['id_etudiant' => $etudiant->id, 'id_seance' => $seance->id, 'id_cours' => $cours->id,
            'id_matiere' => $matiere->id, 'statut' => 'a_faire', 'motif' => 'absence']);
        $payload = ['id_cours_a_faire' => $aFaire->id, 'date_prevue' => '2026-10-15', 'heure_debut' => '08:00', 'heure_fin' => '10:00', 'enseignant_id' => $enseignant->id];
        Sanctum::actingAs($admin);

        return compact('admin', 'enseignant', 'etudiant', 'seance', 'feuille', 'presence', 'aFaire', 'payload');
    }

    public function test_crud_et_visibilite_etudiant(): void
    {
        $data = $this->contexte();
        $this->getJson(self::URL.'/cours-a-rattraper')->assertOk()->assertJsonPath('total', 1);
        $id = $this->postJson(self::URL, $data['payload'])->assertCreated()
            ->assertJsonPath('rattrapage.statut', 'programme')
            ->assertJsonPath('rattrapage.cours_a_faire.id_etudiant', $data['etudiant']->id)->json('rattrapage.id');
        $this->postJson(self::URL, $data['payload'])->assertUnprocessable()->assertJsonValidationErrors('id_cours_a_faire');
        $this->getJson(self::URL.'?id_matiere='.$data['aFaire']->id_matiere)->assertOk()->assertJsonPath('total', 1);
        $this->getJson(self::URL.'/'.$id)->assertOk()->assertJsonPath('rattrapage.id', $id);
        $payload = $data['payload'];
        unset($payload['id_cours_a_faire']);
        $this->putJson(self::URL.'/'.$id, [...$payload, 'date_prevue' => '2026-10-16', 'statut' => 'annule'])->assertOk()
            ->assertJsonPath('rattrapage.date_prevue', '2026-10-16')->assertJsonPath('rattrapage.statut', 'annule');
        $role = Role::create(['code' => 'ETUDIANT', 'libelle' => 'Etudiant']);
        $user = User::factory()->create(['id_role' => $role->id]);
        $data['etudiant']->update(['user_id' => $user->id]);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/etudiant/cours-a-faire')->assertOk()->assertJsonPath('cours_a_faire.0.rattrapage.id', $id);
        Sanctum::actingAs($data['admin']);
        $this->deleteJson(self::URL.'/'.$id)->assertOk();
        $this->getJson(self::URL.'/'.$id)->assertNotFound();
        $this->assertDatabaseHas('cours_a_faire', ['id' => $data['aFaire']->id, 'statut' => 'a_faire']);
        $this->postJson(self::URL, $data['payload'])->assertCreated();
    }

    public function test_secretariat_et_secretaire_academique_peuvent_programmer(): void
    {
        $data = $this->contexte('SECRETARIAT');
        $id = $this->postJson(self::URL, $data['payload'])->assertCreated()->json('rattrapage.id');
        $role = Role::create(['code' => 'SECRETAIRE_ACADEMIQUE', 'libelle' => 'Secretaire']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));
        $this->getJson(self::URL.'/'.$id)->assertOk();
        $payload = $data['payload'];
        unset($payload['id_cours_a_faire']);
        $this->putJson(self::URL.'/'.$id, $payload)->assertOk();
        $this->deleteJson(self::URL.'/'.$id)->assertOk();
    }

    public function test_refuse_absence_non_validee_ou_corrigee_et_cours_termine(): void
    {
        $data = $this->contexte();
        $data['feuille']->update(['statut' => 'brouillon']);
        $this->postJson(self::URL, $data['payload'])->assertUnprocessable();
        $this->getJson(self::URL.'/cours-a-rattraper')->assertOk()->assertJsonPath('total', 0);
        $data['feuille']->update(['statut' => 'validee']);
        $data['presence']->update(['statut' => 'present']);
        $this->postJson(self::URL, $data['payload'])->assertUnprocessable();
        $this->getJson(self::URL.'/cours-a-rattraper')->assertOk()->assertJsonPath('total', 0);
        $data['presence']->update(['statut' => 'absent']);
        $data['aFaire']->update(['statut' => 'termine']);
        $this->postJson(self::URL, $data['payload'])->assertUnprocessable();
        $this->assertDatabaseCount('programmations_rattrapage', 0);
    }

    public function test_validation_horaires_enseignant_date_et_identifiants(): void
    {
        $data = $this->contexte();
        foreach ([['heure_fin' => '07:00'], ['enseignant_id' => $data['admin']->id], ['date_prevue' => '2026-09-13'],
            ['id_salle' => 999999], ['statut' => 'inconnu']] as $changement) {
            $this->postJson(self::URL, [...$data['payload'], ...$changement])->assertUnprocessable()->assertJsonValidationErrors(array_keys($changement));
        }
        $data['enseignant']->update(['is_active' => false]);
        $this->postJson(self::URL, $data['payload'])->assertUnprocessable()->assertJsonValidationErrors('enseignant_id');
        $this->getJson(self::URL.'?per_page=101')->assertUnprocessable();
        $this->getJson(self::URL.'/999999')->assertNotFound();
    }

    public function test_authentification_et_roles(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();
        $data = $this->contexte();
        Sanctum::actingAs($data['enseignant']);
        $this->getJson(self::URL)->assertForbidden();
        $this->postJson(self::URL, $data['payload'])->assertForbidden();
        $this->putJson(self::URL.'/1', $data['payload'])->assertForbidden();
        $this->deleteJson(self::URL.'/1')->assertForbidden();
    }

    public function test_direction_planifie_une_session_et_annule_sans_changer_la_promotion(): void
    {
        $data = $this->contexte('DIRECTION');
        $origine = Promotion::create(['num_promotion' => 4, 'annee_entree' => 2026, 'id_niveau' => $data['seance']->id_niveau]);
        $accueil = Promotion::create(['num_promotion' => 5, 'annee_entree' => 2026, 'id_niveau' => $data['seance']->id_niveau]);
        $data['seance']->update(['id_promotion' => $origine->id]);
        $session = $data['seance']->replicate();
        $session->fill(['date_prevue' => today()->addDays(10)->toDateString(), 'statut' => 'prevue',
            'heure_debut_prevue' => '10:00', 'heure_fin_prevue' => '12:00', 'id_promotion' => $accueil->id]);
        $session->save();
        $this->getJson(self::URL.'/plan?search=ETU-1')->assertOk()
            ->assertJsonPath('statistiques.non_planifies', 1)->assertJsonPath('data.0.statut_plan', 'non_planifie');
        $this->getJson(self::URL.'/cours-a-rattraper/'.$data['aFaire']->id.'/sessions')->assertOk()
            ->assertJsonCount(1, 'sessions')->assertJsonPath('sessions.0.id', $session->id);
        $payload = ['id_cours_a_faire' => $data['aFaire']->id, 'id_seance_rattrapage' => $session->id];
        $this->postJson(self::URL, [...$payload, 'id_seance_rattrapage' => $data['seance']->id])->assertUnprocessable();
        $this->postJson(self::URL, [...$payload, 'enseignant_id' => $data['enseignant']->id])->assertUnprocessable();
        $id = $this->postJson(self::URL.'/inscrire', $payload)->assertCreated()
            ->assertJsonPath('rattrapage.id_seance_rattrapage', $session->id)
            ->assertJsonPath('rattrapage.session.promotion.id', $accueil->id)
            ->assertJsonPath('rattrapage.created_by', $data['admin']->id)->json('rattrapage.id');
        $this->getJson(self::URL.'/plan?statut=inscrit')->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('statistiques.inscrits', 1)
            ->assertJsonPath('data.0.promotion_origine.id', $origine->id);
        $this->getJson(self::URL.'/plan?id_promotion='.$accueil->id)->assertOk()->assertJsonPath('total', 0);
        $this->assertDatabaseHas('cours_a_faire', ['id' => $data['aFaire']->id, 'id_seance' => $data['seance']->id, 'statut' => 'a_faire']);
        $this->assertDatabaseHas('presences', ['id' => $data['presence']->id, 'statut' => 'absent']);
        $this->postJson(self::URL.'/'.$id.'/annuler')->assertOk()->assertJsonPath('rattrapage.statut', 'annule');
        $this->postJson(self::URL.'/'.$id.'/annuler')->assertUnprocessable();
        $this->getJson(self::URL.'/plan')->assertOk()->assertJsonPath('statistiques.non_planifies', 1);
        $this->putJson(self::URL.'/'.$id, ['id_seance_rattrapage' => $session->id])->assertOk()
            ->assertJsonPath('rattrapage.statut', 'programme');
        $session->update(['id_cours' => null]);
        $this->putJson(self::URL.'/'.$id, ['id_seance_rattrapage' => $session->id])->assertUnprocessable();
        Sanctum::actingAs($data['enseignant']);
        $this->getJson(self::URL.'/plan')->assertForbidden();
        $this->postJson(self::URL.'/'.$id.'/annuler')->assertForbidden();
    }

    public function test_session_matiere_sans_cours_et_exclusion_sessions_passees(): void
    {
        $data = $this->contexte('SECRETARIAT');
        $data['aFaire']->update(['id_cours' => null]);
        $data['seance']->update(['id_cours' => null]);
        $session = $data['seance']->replicate();
        $session->fill(['date_prevue' => today()->addDays(7)->toDateString(), 'statut' => 'prevue',
            'heure_debut_prevue' => '10:00', 'heure_fin_prevue' => '12:00']);
        $session->save();
        $payload = ['id_cours_a_faire' => $data['aFaire']->id, 'id_seance_rattrapage' => $session->id];
        $session->update(['date_prevue' => today()->subDay()->toDateString()]);
        $this->postJson(self::URL, $payload)->assertUnprocessable();
        $session->update(['date_prevue' => today()->addDays(7)->toDateString()]);
        $this->postJson(self::URL, $payload)->assertCreated();
    }
}
