<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Creneau;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Role;
use App\Models\Salle;
use App\Models\SeanceCahierTexte;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CahierTexteEnseignantApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function contexte(bool $creerSeances = true): array
    {
        Carbon::setTestNow('2026-09-14 10:00:00');
        $adminRole = Role::create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        $enseignantRole = Role::create(['code' => 'ENSEIGNANT', 'libelle' => 'Enseignant']);
        $admin = User::factory()->create(['id_role' => $adminRole->id]);
        $enseignant = User::factory()->create(['id_role' => $enseignantRole->id]);
        $autre = User::factory()->create(['id_role' => $enseignantRole->id]);
        $annee = AnneeAcademique::create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'active' => true]);
        $module = $annee->calendrier()->create([])->modules()->create(['libelle' => 'Module 1', 'ordre' => 1, 'date_debut' => '2026-09-01', 'date_fin' => '2026-09-30']);
        $niveau = Niveau::create(['code' => 'N1', 'libelle' => '1ère Année', 'rang' => 1]);
        $matiere = Matiere::create(['code' => 'MAT-1', 'libelle' => 'Théologie', 'id_niveau' => $niveau->id]);
        $salle = Salle::create(['nom' => 'Salle A', 'code' => 'A']);
        $creneau = Creneau::create(['id_module_calendrier' => $module->id, 'id_niveau' => $niveau->id, 'id_matiere' => $matiere->id,
            'enseignant_id' => $enseignant->id, 'id_salle' => $salle->id, 'jour' => 1, 'heure_debut' => '08:00:00', 'heure_fin' => '10:00:00']);
        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/administration/publication-programme/'.$module->id_calendrier.'/publier')->assertOk();
        if ($creerSeances) {
            Carbon::setTestNow('2026-09-07 10:00:00');
            Sanctum::actingAs($enseignant);
            foreach (['2026-09-07', '2026-09-14', '2026-09-21', '2026-09-28'] as $date) {
                $this->postJson('/api/v1/enseignant/cahier-de-texte', [
                    'id_creneau' => $creneau->id, 'date_prevue' => $date, 'statut' => 'prevue',
                ])->assertCreated();
            }
            Carbon::setTestNow('2026-09-14 10:00:00');
        }

        return compact('admin', 'enseignant', 'autre', 'creneau');
    }

    public function test_enseignant_consulte_uniquement_les_seances_qu_il_a_creees(): void
    {
        $data = $this->contexte();
        $this->assertDatabaseCount('seances_cahier_texte', 4);
        Sanctum::actingAs($data['enseignant']);
        $this->getJson('/api/v1/enseignant/cahier-de-texte?date_debut=2026-09-01&date_fin=2026-09-30')->assertOk()
            ->assertJsonPath('meta.total', 4)->assertJsonPath('seances.0.statut', 'prevue');
    }

    public function test_creneaux_et_publications_ne_creent_aucune_seance(): void
    {
        $data = $this->contexte(false);
        $this->assertDatabaseCount('seances_cahier_texte', 0);
        $payload = $data['creneau']->only(['id_module_calendrier', 'id_niveau', 'id_matiere', 'enseignant_id', 'id_salle']);
        $this->postJson('/api/v1/parametres/creneaux', [...$payload, 'jour' => 2, 'heure_debut' => '08:00', 'heure_fin' => '10:00'])->assertCreated();
        $this->patchJson('/api/v1/parametres/creneaux/'.$data['creneau']->id, ['heure_fin' => '11:00'])->assertOk();
        $url = '/api/v1/administration/publication-programme/'.$data['creneau']->moduleCalendrier->id_calendrier;
        $this->postJson($url.'/publier')->assertOk();
        $this->assertDatabaseCount('seances_cahier_texte', 0);
        Sanctum::actingAs($data['autre']);
        $seance = ['id_creneau' => $data['creneau']->id, 'date_prevue' => '2026-09-14', 'statut' => 'prevue'];
        $this->postJson('/api/v1/enseignant/cahier-de-texte', $seance)->assertNotFound();
        Sanctum::actingAs($data['enseignant']);
        $id = $this->postJson('/api/v1/enseignant/cahier-de-texte', $seance)->assertCreated()
            ->assertJsonPath('seance.source', 'manuelle')->assertJsonPath('seance.created_by', $data['enseignant']->id)->json('seance.id');
        $avant = SeanceCahierTexte::findOrFail($id)->toArray();
        Sanctum::actingAs($data['admin']);
        $this->postJson($url.'/retire')->assertOk();
        $this->postJson($url.'/publier')->assertOk();
        $this->assertDatabaseCount('seances_cahier_texte', 1);
        $this->assertSame($avant, SeanceCahierTexte::findOrFail($id)->toArray());
    }

    public function test_enseignant_consigne_modifie_et_consulte_une_seance(): void
    {
        $data = $this->contexte();
        $seance = SeanceCahierTexte::whereDate('date_prevue', '2026-09-14')->firstOrFail();
        Sanctum::actingAs($data['enseignant']);
        $payload = ['statut' => 'realisee', 'date_effective' => '2026-09-14', 'heure_effective' => '08:00',
            'duree_reelle_minutes' => 120, 'theme_traite' => 'Les conciles', 'observations' => 'Participation active', 'supports_pedagogiques' => 'Support PDF'];
        $this->patchJson('/api/v1/enseignant/cahier-de-texte/'.$seance->id, $payload)->assertOk()
            ->assertJsonPath('seance.theme_traite', 'Les conciles')->assertJsonPath('seance.modifiable', true);
        $this->getJson('/api/v1/enseignant/cahier-de-texte/'.$seance->id)->assertOk()
            ->assertJsonPath('seance.duree_reelle_minutes', 120);
    }

    public function test_valide_les_champs_obligatoires_et_verrouille_apres_48_heures(): void
    {
        $data = $this->contexte();
        Sanctum::actingAs($data['enseignant']);
        $courante = SeanceCahierTexte::whereDate('date_prevue', '2026-09-14')->firstOrFail();
        $this->patchJson('/api/v1/enseignant/cahier-de-texte/'.$courante->id, ['statut' => 'realisee'])
            ->assertUnprocessable()->assertJsonValidationErrors('statut');
        $this->patchJson('/api/v1/enseignant/cahier-de-texte/'.$courante->id, ['statut' => 'annulee'])
            ->assertUnprocessable()->assertJsonValidationErrors('motif_annulation');
        $ancienne = SeanceCahierTexte::whereDate('date_prevue', '2026-09-07')->firstOrFail();
        $this->patchJson('/api/v1/enseignant/cahier-de-texte/'.$ancienne->id, ['statut' => 'reportee'])
            ->assertUnprocessable()->assertJsonValidationErrors('seance');
        $this->getJson('/api/v1/enseignant/cahier-de-texte/'.$ancienne->id)->assertOk()->assertJsonPath('seance.verrouillee', true);
    }

    public function test_un_enseignant_ne_peut_pas_acceder_aux_seances_d_un_autre(): void
    {
        $data = $this->contexte();
        $seance = SeanceCahierTexte::firstOrFail();
        Sanctum::actingAs($data['autre']);
        $this->getJson('/api/v1/enseignant/cahier-de-texte/'.$seance->id)->assertNotFound();
        $this->patchJson('/api/v1/enseignant/cahier-de-texte/'.$seance->id, ['statut' => 'reportee'])->assertNotFound();
        $this->deleteJson('/api/v1/enseignant/cahier-de-texte/'.$seance->id)->assertNotFound();
    }

    public function test_peut_creer_et_supprimer_une_seance_hors_planning_dans_le_module(): void
    {
        $data = $this->contexte();
        Sanctum::actingAs($data['enseignant']);
        $id = $this->postJson('/api/v1/enseignant/cahier-de-texte', [
            'id_creneau' => $data['creneau']->id, 'date_prevue' => '2026-09-15', 'statut' => 'prevue',
        ])->assertCreated()->assertJsonPath('seance.source', 'manuelle')->json('seance.id');
        $this->deleteJson('/api/v1/enseignant/cahier-de-texte/'.$id)->assertOk();
        $this->assertSoftDeleted('seances_cahier_texte', ['id' => $id]);
    }

    public function test_authentification_role_filtres_et_pagination(): void
    {
        $this->getJson('/api/v1/enseignant/cahier-de-texte')->assertUnauthorized();
        $data = $this->contexte();
        Sanctum::actingAs($data['admin']);
        $this->getJson('/api/v1/enseignant/cahier-de-texte')->assertForbidden();
        Sanctum::actingAs($data['enseignant']);
        $this->getJson('/api/v1/enseignant/cahier-de-texte?date_debut=2026-09-01&date_fin=2026-09-30&statut=prevue&per_page=2&page=2')
            ->assertOk()->assertJsonPath('meta.total', 4)->assertJsonPath('meta.current_page', 2)->assertJsonCount(2, 'seances');
        $this->getJson('/api/v1/enseignant/cahier-de-texte?statut=inconnu')->assertUnprocessable()->assertJsonValidationErrors('statut');
        $this->getJson('/api/v1/enseignant/cahier-de-texte?date_debut=2026-09-20&date_fin=2026-09-01')->assertUnprocessable()->assertJsonValidationErrors('date_fin');
    }

    public function test_refuse_doublon_et_suppression_hors_delai(): void
    {
        $data = $this->contexte();
        Sanctum::actingAs($data['enseignant']);
        $this->postJson('/api/v1/enseignant/cahier-de-texte', [
            'id_creneau' => $data['creneau']->id, 'date_prevue' => '2026-09-14', 'statut' => 'prevue',
        ])->assertUnprocessable()->assertJsonValidationErrors('date_prevue');
        $ancienne = SeanceCahierTexte::whereDate('date_prevue', '2026-09-07')->firstOrFail();
        $this->deleteJson('/api/v1/enseignant/cahier-de-texte/'.$ancienne->id)
            ->assertUnprocessable()->assertJsonValidationErrors('seance');
        $this->assertDatabaseHas('seances_cahier_texte', ['id' => $ancienne->id, 'deleted_at' => null]);
    }

    public function test_retrait_cache_les_seances_prevues_et_republication_preserve_les_seances_realisees(): void
    {
        $data = $this->contexte();
        $seance = SeanceCahierTexte::whereDate('date_prevue', '2026-09-14')->firstOrFail();
        Sanctum::actingAs($data['enseignant']);
        $this->patchJson('/api/v1/enseignant/cahier-de-texte/'.$seance->id, [
            'statut' => 'realisee', 'date_effective' => '2026-09-14', 'heure_effective' => '08:00',
            'duree_reelle_minutes' => 120, 'theme_traite' => 'Thème officiel',
        ])->assertOk();
        Sanctum::actingAs($data['admin']);
        $calendrierId = $data['creneau']->moduleCalendrier->id_calendrier;
        $this->postJson('/api/v1/administration/publication-programme/'.$calendrierId.'/retire')->assertOk();
        Sanctum::actingAs($data['enseignant']);
        $this->getJson('/api/v1/enseignant/cahier-de-texte?date_debut=2026-09-01&date_fin=2026-09-30')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('seances.0.theme_traite', 'Thème officiel');
        Sanctum::actingAs($data['admin']);
        $this->postJson('/api/v1/administration/publication-programme/'.$calendrierId.'/publier')->assertOk();
        $this->assertDatabaseHas('seances_cahier_texte', ['id' => $seance->id, 'statut' => 'realisee', 'theme_traite' => 'Thème officiel']);
        $this->assertDatabaseCount('seances_cahier_texte', 4);
    }
}
