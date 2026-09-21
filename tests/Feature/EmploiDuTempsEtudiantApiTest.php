<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Creneau;
use App\Models\Etudiant;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\Salle;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmploiDuTempsEtudiantApiTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/etudiant/emploi-du-temps';

    private function contexte(): array
    {
        $role = Role::create(['code' => 'ETUDIANT', 'libelle' => 'Etudiant']);
        $user = User::factory()->create(['id_role' => $role->id]);
        Sanctum::actingAs($user);
        $etudiant = Etudiant::create(['user_id' => $user->id, 'matricule' => 'ETU-1', 'nom' => 'KONE', 'prenoms' => 'Test', 'date_inscription' => '2026-09-01']);
        $annee = AnneeAcademique::create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'active' => true]);
        $calendrier = $annee->calendrier()->create([]);
        $modules = collect([1, 2])->map(fn ($ordre) => $calendrier->modules()->create(['libelle' => 'Module '.$ordre, 'ordre' => $ordre, 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31']));
        $niveau = Niveau::create(['code' => 'N1', 'libelle' => 'Niveau 1', 'rang' => 1]);
        $autreNiveau = Niveau::create(['code' => 'N2', 'libelle' => 'Niveau 2', 'rang' => 2]);
        $promotion = Promotion::create(['num_promotion' => 1, 'annee_entree' => 2026, 'id_niveau' => $niveau->id]);
        $autrePromotion = Promotion::create(['num_promotion' => 2, 'annee_entree' => 2026, 'id_niveau' => $niveau->id]);
        $etudiant->inscriptions()->create(['id_promotion' => $promotion->id, 'id_annee_academique' => $annee->id, 'date_inscription' => '2026-09-01']);
        $teacherRole = Role::create(['code' => 'ENSEIGNANT', 'libelle' => 'Enseignant']);
        $teacher = User::factory()->create(['id_role' => $teacherRole->id]);
        $matiere = Matiere::create(['code' => 'MAT-1', 'libelle' => 'Theologie', 'id_niveau' => $niveau->id]);
        $salle = Salle::create(['code' => 'A', 'nom' => 'Salle A']);
        $base = ['id_module_calendrier' => $modules[0]->id, 'id_niveau' => $niveau->id, 'id_matiere' => $matiere->id, 'enseignant_id' => $teacher->id, 'id_salle' => $salle->id, 'jour' => 1, 'heure_debut' => '08:00:00', 'heure_fin' => '10:00:00'];
        $prive = Creneau::create([...$base, 'id_promotion' => $promotion->id]);
        $commun = Creneau::create([...$base, 'id_module_calendrier' => $modules[1]->id, 'jour' => 2]);
        Creneau::create([...$base, 'id_promotion' => $autrePromotion->id]);
        Creneau::create([...$base, 'id_niveau' => $autreNiveau->id]);
        $publication = $calendrier->publication()->create(['statut' => 'publie', 'version' => 1, 'date_publication' => now()]);

        return compact('user', 'etudiant', 'annee', 'modules', 'publication', 'prive', 'commun', 'teacher');
    }

    public function test_selection_promotion_cours_communs_et_filtre_module(): void
    {
        $c = $this->contexte();
        $response = $this->getJson(self::URL)->assertOk()->assertJsonPath('nombre_creneaux', 2)
            ->assertJsonPath('heures_hebdomadaires', 4)->assertJsonPath('jours.0.libelle', 'Lundi')
            ->assertJsonPath('creneaux.0.enseignant.id', $c['teacher']->id)
            ->assertJsonPath('creneaux.0.salle.nom', 'Salle A')->assertJsonCount(2, 'modules_disponibles');
        $this->assertSame([$c['prive']->id, $c['commun']->id], array_column($response->json('creneaux'), 'id'));
        $this->getJson(self::URL.'?id_module_calendrier='.$c['modules'][1]->id)->assertOk()
            ->assertJsonPath('nombre_creneaux', 1)->assertJsonPath('creneaux.0.id', $c['commun']->id);
    }

    public function test_programme_retire_et_absence_inscription_ne_divulguent_aucun_creneau(): void
    {
        $c = $this->contexte();
        $c['publication']->update(['statut' => 'non_publie']);
        $this->getJson(self::URL)->assertOk()->assertJsonCount(0, 'creneaux')->assertJsonCount(0, 'modules_disponibles');
        $c['publication']->update(['statut' => 'publie']);
        $c['etudiant']->inscriptions()->delete();
        $this->getJson(self::URL)->assertOk()->assertJsonCount(0, 'creneaux');
    }

    public function test_annees_et_modules_sont_isoles(): void
    {
        $c = $this->contexte();
        $autre = AnneeAcademique::create(['libelle' => '2027-2028', 'date_debut' => '2027-09-01', 'date_fin' => '2028-07-31']);
        $url = self::URL.'?id_annee_academique='.$autre->id;
        $this->getJson($url)->assertOk()->assertJsonCount(0, 'creneaux');
        $this->getJson($url.'&id_module_calendrier='.$c['modules'][0]->id)->assertUnprocessable();
        $this->getJson(self::URL.'?id_annee_academique=999999')->assertUnprocessable();
        $this->getJson(self::URL.'?id_module_calendrier=999999')->assertUnprocessable();
    }

    public function test_authentification_role_compte_actif_et_fiche_etudiant(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();
        $c = $this->contexte();
        Sanctum::actingAs($c['teacher']);
        $this->getJson(self::URL)->assertForbidden();
        Sanctum::actingAs(User::factory()->create(['id_role' => $c['user']->id_role]));
        $this->getJson(self::URL)->assertNotFound();
        $c['user']->update(['is_active' => false]);
        Sanctum::actingAs($c['user']);
        $this->getJson(self::URL)->assertForbidden();
    }

    public function test_un_autre_etudiant_ne_peut_pas_usurper_le_planning_par_parametres(): void
    {
        $c = $this->contexte();
        $user = User::factory()->create(['id_role' => $c['user']->id_role]);
        $etudiant = Etudiant::create(['user_id' => $user->id, 'matricule' => 'ETU-2', 'nom' => 'AUTRE', 'prenoms' => 'Test', 'date_inscription' => '2026-09-01']);
        $promotion = Promotion::where('num_promotion', 2)->firstOrFail();
        $etudiant->inscriptions()->create(['id_promotion' => $promotion->id, 'id_annee_academique' => $c['annee']->id, 'date_inscription' => '2026-09-01']);
        Sanctum::actingAs($user);
        $response = $this->getJson(self::URL.'?id_etudiant='.$c['etudiant']->id.'&id_promotion='.$c['prive']->id_promotion)
            ->assertOk()->assertJsonPath('etudiant.id', $etudiant->id)->assertJsonPath('promotion.id', $promotion->id)
            ->assertJsonPath('nombre_creneaux', 2);
        $ids = array_column($response->json('creneaux'), 'id');
        $this->assertNotContains($c['prive']->id, $ids);
        $this->assertContains($c['commun']->id, $ids);
    }

    public function test_annee_courante_sans_annee_active_et_absence_annee(): void
    {
        $c = $this->contexte();
        $this->travelTo(Carbon::parse('2026-10-01'));
        $c['annee']->update(['active' => false]);
        $this->getJson(self::URL)->assertOk()->assertJsonPath('annee_academique.id', $c['annee']->id)->assertJsonPath('nombre_creneaux', 2);
        $this->travelTo(Carbon::parse('2035-01-01'));
        $this->getJson(self::URL)->assertOk()->assertJsonPath('annee_academique', null)
            ->assertJsonPath('nombre_creneaux', 0)->assertJsonPath('heures_hebdomadaires', 0)
            ->assertJsonCount(0, 'jours')->assertJsonCount(0, 'creneaux');
        $this->getJson(self::URL.'?id_annee_academique='.$c['annee']->id)->assertOk()->assertJsonPath('nombre_creneaux', 2);
        $this->travelBack();
    }

    public function test_suppression_creneau_et_duree_fractionnaire(): void
    {
        $c = $this->contexte();
        $c['prive']->delete();
        $c['commun']->update(['heure_fin' => '09:30:00']);
        $this->getJson(self::URL)->assertOk()->assertJsonPath('nombre_creneaux', 1)
            ->assertJsonPath('heures_hebdomadaires', 1.5)->assertJsonPath('creneaux.0.duree_minutes', 90)
            ->assertJsonPath('jours.0.libelle', 'Mardi')->assertJsonCount(1, 'jours');
    }

    public function test_sans_publication_et_annee_supprimee(): void
    {
        $c = $this->contexte();
        $c['publication']->delete();
        $this->getJson(self::URL)->assertOk()->assertJsonPath('publication.statut', 'non_publie')
            ->assertJsonCount(0, 'creneaux')->assertJsonCount(0, 'modules_disponibles');
        $c['annee']->delete();
        $this->getJson(self::URL.'?id_annee_academique='.$c['annee']->id)->assertUnprocessable()
            ->assertJsonValidationErrors('id_annee_academique');
    }

    public function test_inscription_ancienne_ne_donne_pas_acces_au_planning_actuel(): void
    {
        $c = $this->contexte();
        $ancienne = AnneeAcademique::create(['libelle' => '2025-2026', 'date_debut' => '2025-09-01', 'date_fin' => '2026-07-31']);
        $c['etudiant']->inscriptions()->update(['id_annee_academique' => $ancienne->id]);
        $this->getJson(self::URL)->assertOk()->assertJsonPath('promotion', null)->assertJsonCount(0, 'creneaux');
    }
}
