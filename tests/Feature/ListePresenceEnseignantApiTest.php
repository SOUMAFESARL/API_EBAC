<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Cours;
use App\Models\Creneau;
use App\Models\Etudiant;
use App\Models\Inscription;
use App\Models\Matiere;
use App\Models\Module;
use App\Models\Niveau;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\Salle;
use App\Models\SeanceCahierTexte;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ListePresenceEnseignantApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function contexte(): array
    {
        Carbon::setTestNow('2026-09-14 10:00:00');
        $adminRole = Role::create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        $teacherRole = Role::create(['code' => 'ENSEIGNANT', 'libelle' => 'Enseignant']);
        $admin = User::factory()->create(['id_role' => $adminRole->id]);
        $enseignant = User::factory()->create(['id_role' => $teacherRole->id]);
        $autreEnseignant = User::factory()->create(['id_role' => $teacherRole->id]);
        $annee = AnneeAcademique::create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'active' => true]);
        $anneePrecedente = AnneeAcademique::create(['libelle' => '2025-2026', 'date_debut' => '2025-09-01', 'date_fin' => '2026-07-31', 'active' => false]);
        $moduleCalendrier = $annee->calendrier()->create([])->modules()->create(['libelle' => 'Module 1', 'ordre' => 1, 'date_debut' => '2026-09-01', 'date_fin' => '2026-09-30']);
        $niveau = Niveau::create(['code' => 'N1', 'libelle' => '1ère Année', 'rang' => 1]);
        $promotion = Promotion::create(['num_promotion' => 4, 'annee_entree' => 2026, 'id_niveau' => $niveau->id]);
        $autrePromotion = Promotion::create(['num_promotion' => 5, 'annee_entree' => 2026, 'id_niveau' => $niveau->id]);
        $matiere = Matiere::create(['code' => 'MAT-1', 'libelle' => 'Théologie', 'id_niveau' => $niveau->id]);
        $module = Module::create(['id_matiere' => $matiere->id, 'libelle' => 'Doctrine', 'ordre' => 1]);
        $cours = Cours::create(['id_module' => $module->id, 'code' => 'C-1', 'libelle' => 'La Trinité', 'ordre' => 1]);
        $salle = Salle::create(['nom' => 'Amphithéâtre A', 'code' => 'A']);
        $creneau = Creneau::create(['id_module_calendrier' => $moduleCalendrier->id, 'id_niveau' => $niveau->id, 'id_matiere' => $matiere->id,
            'id_cours' => $cours->id, 'id_promotion' => $promotion->id, 'enseignant_id' => $enseignant->id, 'id_salle' => $salle->id,
            'jour' => 1, 'heure_debut' => '08:00:00', 'heure_fin' => '10:00:00']);
        $etudiants = collect([['ETU-1', 'KOUAME'], ['ETU-2', 'N GUESSAN']])->map(function ($item) use ($promotion, $annee) {
            $etudiant = Etudiant::create(['matricule' => $item[0], 'nom' => $item[1], 'prenoms' => 'Test', 'date_inscription' => '2026-09-01']);
            Inscription::create(['id_etudiant' => $etudiant->id, 'id_promotion' => $promotion->id, 'id_annee_academique' => $annee->id, 'date_inscription' => '2026-09-01']);

            return $etudiant;
        });
        $horsPromotion = Etudiant::create(['matricule' => 'ETU-3', 'nom' => 'HORS', 'prenoms' => 'Promotion', 'date_inscription' => '2026-09-01']);
        $etudiants[1]->inscriptions()->update(['id_annee_academique' => $anneePrecedente->id, 'date_inscription' => '2025-09-01']);
        Inscription::create(['id_etudiant' => $horsPromotion->id, 'id_promotion' => $autrePromotion->id, 'id_annee_academique' => $annee->id, 'date_inscription' => '2026-09-01']);
        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/administration/publication-programme/'.$moduleCalendrier->id_calendrier.'/publier')->assertOk();
        Sanctum::actingAs($enseignant);
        $this->postJson('/api/v1/enseignant/cahier-de-texte', [
            'id_creneau' => $creneau->id, 'date_prevue' => '2026-09-14', 'statut' => 'prevue',
        ])->assertCreated();
        $seance = SeanceCahierTexte::whereDate('date_prevue', '2026-09-14')->firstOrFail();
        $seance->update(['statut' => 'realisee', 'date_effective' => '2026-09-14', 'heure_effective' => '08:00', 'duree_reelle_minutes' => 120, 'theme_traite' => 'Les conciles']);

        return compact('admin', 'enseignant', 'autreEnseignant', 'seance', 'etudiants', 'horsPromotion');
    }

    public function test_affiche_tous_les_etudiants_de_la_promotion_sans_filtre_annee(): void
    {
        $data = $this->contexte();
        Sanctum::actingAs($data['enseignant']);
        $this->getJson('/api/v1/enseignant/liste-presence/'.$data['seance']->id)->assertOk()
            ->assertJsonCount(2, 'feuille_presence.etudiants')
            ->assertJsonMissing(['matricule' => $data['horsPromotion']->matricule]);
    }

    public function test_liste_seances_pour_enseignant(): void
    {
        $data = $this->contexte();
        Sanctum::actingAs($data['enseignant']);
        $this->getJson('/api/v1/enseignant/liste-presence')->assertOk()
            ->assertJsonCount(1, 'seances')
            ->assertJsonPath('nombre_seances', 1)
            ->assertJsonCount(2, 'seances.0.etudiants')
            ->assertJsonMissing(['matricule' => $data['horsPromotion']->matricule]);
    }

    public function test_enregistre_et_valide_definitivement_la_presence_et_cree_le_cours_a_faire(): void
    {
        $data = $this->contexte();
        Sanctum::actingAs($data['enseignant']);
        $presences = [['id_etudiant' => $data['etudiants'][0]->id, 'statut' => 'present'], ['id_etudiant' => $data['etudiants'][1]->id, 'statut' => 'absent']];
        $url = '/api/v1/enseignant/liste-presence/'.$data['seance']->id;
        $this->putJson($url, compact('presences'))->assertOk()
            ->assertJsonPath('feuille_presence.presence.absents', 1)
            ->assertJsonPath('feuille_presence.presence.statut', 'validee')
            ->assertJsonPath('feuille_presence.modifiable', false)
            ->assertJsonPath('feuille_presence.date_validation', now()->toJSON());
        $this->assertDatabaseHas('feuilles_presence', ['id_seance' => $data['seance']->id,
            'statut' => 'validee', 'validee_par' => $data['enseignant']->id]);
        $this->assertDatabaseHas('cours_a_faire', ['id_etudiant' => $data['etudiants'][1]->id, 'id_seance' => $data['seance']->id, 'statut' => 'a_faire']);
        $this->putJson($url, compact('presences'))->assertUnprocessable()->assertJsonValidationErrors('presences');
        $this->postJson($url.'/valider')->assertUnprocessable()->assertJsonValidationErrors('presences');
    }

    public function test_put_refuse_seance_non_realisee_et_presences_manquantes(): void
    {
        $data = $this->contexte();
        $url = '/api/v1/enseignant/liste-presence/'.$data['seance']->id;
        $this->putJson($url, [])->assertUnprocessable()->assertJsonValidationErrors('presences');
        $data['seance']->update(['statut' => 'prevue']);
        $presences = $data['etudiants']->map(fn ($e) => ['id_etudiant' => $e->id, 'statut' => 'absent'])->all();
        $this->putJson($url, compact('presences'))->assertUnprocessable()->assertJsonValidationErrors('seance');
        $this->assertDatabaseMissing('feuilles_presence', ['id_seance' => $data['seance']->id]);
        $this->assertDatabaseMissing('cours_a_faire', ['id_seance' => $data['seance']->id]);
    }

    public function test_rejet_administration_remet_presence_a_transmettre(): void
    {
        $data = $this->contexte();
        $seance = $data['seance'];
        $url = '/api/v1/enseignant/liste-presence/'.$seance->id;
        $presences = $data['etudiants']->map(fn ($e) => ['id_etudiant' => $e->id, 'statut' => 'present'])->all();
        $notes = \App\Models\FeuilleNotes::create(['id_seance' => $seance->id,
            'id_annee_academique' => $seance->moduleCalendrier->calendrier->id_annee_academique,
            'id_matiere' => $seance->id_matiere, 'id_promotion' => $seance->id_promotion,
            'updated_by' => $data['enseignant']->id, 'transmise_par' => $data['enseignant']->id]);
        foreach (['secretariat' => 'transmise', 'direction' => 'transmise_direction'] as $origine => $statut) {
            $this->postJson($url.'/valider', compact('presences'))->assertOk();
            $notes->update(['statut' => $statut]);
            Sanctum::actingAs($data['admin']);
            $this->postJson('/api/v1/administration/notes-transmises/'.$notes->id.'/rejeter-'.$origine,
                ['motif' => 'Presences incorrectes'])->assertOk();
            Sanctum::actingAs($data['enseignant']);
            $this->getJson($url)->assertOk()->assertJsonPath('feuille_presence.presence.statut', 'a_transmettre')
                ->assertJsonPath('feuille_presence.modifiable', true)->assertJsonPath('feuille_presence.date_validation', null);
            $this->getJson('/api/v1/enseignant/liste-presence')->assertOk()
                ->assertJsonPath('seances.0.presence.statut', 'a_transmettre');
            $this->assertDatabaseHas('feuilles_presence', ['id_seance' => $seance->id,
                'statut' => 'brouillon', 'validee_par' => null]);
        }
    }

    public function test_rejet_notes_autorise_correction_et_revalidation_des_presences(): void
    {
        $data = $this->contexte();
        $seance = $data['seance'];
        $url = '/api/v1/enseignant/liste-presence/'.$seance->id;
        $presences = [['id_etudiant' => $data['etudiants'][0]->id, 'statut' => 'present'],
            ['id_etudiant' => $data['etudiants'][1]->id, 'statut' => 'present']];
        $this->postJson($url.'/valider', compact('presences'))->assertOk();
        $notes = \App\Models\FeuilleNotes::create(['id_seance' => $seance->id,
            'id_annee_academique' => $seance->moduleCalendrier->calendrier->id_annee_academique,
            'id_matiere' => $seance->id_matiere, 'id_promotion' => $seance->id_promotion,
            'transmise_par' => $data['enseignant']->id, 'updated_by' => $data['enseignant']->id, 'statut' => 'transmise']);
        foreach (['rejetee_secretariat', 'rejetee_direction'] as $statut) {
            $notes->update(['statut' => $statut]);
            $this->getJson($url)->assertOk()->assertJsonPath('feuille_presence.modifiable', true)
                ->assertJsonPath('feuille_presence.presence.statut', 'a_transmettre');
            $this->getJson('/api/v1/enseignant/liste-presence')->assertOk()
                ->assertJsonPath('seances.0.presence.statut', 'a_transmettre');
            $presences[1]['statut'] = 'absent';
            $this->putJson($url, compact('presences'))->assertOk()
                ->assertJsonPath('feuille_presence.presence.statut', 'a_transmettre')
                ->assertJsonPath('feuille_presence.date_validation', now()->toJSON());
            $this->assertDatabaseHas('feuilles_presence', ['id_seance' => $seance->id, 'statut' => 'validee']);
            $notes->update(['statut' => 'transmise']);
            $this->getJson($url)->assertOk()->assertJsonPath('feuille_presence.modifiable', false);
            $this->putJson($url, compact('presences'))->assertUnprocessable();
            Sanctum::actingAs($data['autreEnseignant']);
            $this->putJson($url, compact('presences'))->assertNotFound();
            Sanctum::actingAs($data['enseignant']);
        }
    }

    public function test_refuse_liste_incomplete_doublon_et_etudiant_non_concerne(): void
    {
        $data = $this->contexte();
        Sanctum::actingAs($data['enseignant']);
        $url = '/api/v1/enseignant/liste-presence/'.$data['seance']->id;
        foreach ([
            [['id_etudiant' => $data['etudiants'][0]->id, 'statut' => 'present']],
            [['id_etudiant' => $data['etudiants'][0]->id, 'statut' => 'present'], ['id_etudiant' => $data['etudiants'][0]->id, 'statut' => 'absent']],
            [['id_etudiant' => $data['etudiants'][0]->id, 'statut' => 'present'], ['id_etudiant' => $data['horsPromotion']->id, 'statut' => 'absent']],
        ] as $presences) {
            $this->putJson($url, compact('presences'))->assertUnprocessable()->assertJsonValidationErrors('presences');
        }
    }

    public function test_controle_authentification_role_et_proprietaire(): void
    {
        $this->getJson('/api/v1/enseignant/liste-presence')->assertUnauthorized();
        $data = $this->contexte();
        Sanctum::actingAs($data['admin']);
        $this->getJson('/api/v1/enseignant/liste-presence')->assertForbidden();
        Sanctum::actingAs($data['autreEnseignant']);
        $this->getJson('/api/v1/enseignant/liste-presence/'.$data['seance']->id)->assertNotFound();
    }

    public function test_inscription_apres_seance_est_exclue_et_ne_genere_pas_absence(): void
    {
        $data = $this->contexte();
        $data['etudiants'][1]->inscriptions()->update(['date_inscription' => '2026-09-15']);
        $data['etudiants'][0]->inscriptions()->update(['date_inscription' => '2026-09-14']);
        $url = '/api/v1/enseignant/liste-presence/'.$data['seance']->id;

        $this->getJson($url)->assertOk()->assertJsonCount(1, 'feuille_presence.etudiants')
            ->assertJsonPath('feuille_presence.etudiants.0.id', $data['etudiants'][0]->id);
        $this->getJson('/api/v1/enseignant/liste-presence')->assertOk()->assertJsonCount(1, 'seances.0.etudiants');
        $this->putJson($url, ['presences' => [
            ['id_etudiant' => $data['etudiants'][0]->id, 'statut' => 'present'],
            ['id_etudiant' => $data['etudiants'][1]->id, 'statut' => 'absent'],
        ]])->assertUnprocessable();
        $this->putJson($url, ['presences' => [
            ['id_etudiant' => $data['etudiants'][0]->id, 'statut' => 'present'],
        ]])->assertOk();
        $this->assertDatabaseHas('feuilles_presence', ['id_seance' => $data['seance']->id, 'statut' => 'validee']);
        $this->assertDatabaseMissing('presences', ['id_etudiant' => $data['etudiants'][1]->id]);
        $this->assertDatabaseMissing('cours_a_faire', ['id_etudiant' => $data['etudiants'][1]->id]);
    }

    public function test_liste_distingue_dates_seances_et_utilise_date_effective(): void
    {
        $data = $this->contexte();
        $data['etudiants'][1]->inscriptions()->update(['date_inscription' => '2026-09-15']);
        $ulterieure = $data['seance']->replicate();
        $ulterieure->fill(['date_prevue' => '2026-09-21', 'date_effective' => '2026-09-21'])->save();
        $this->getJson('/api/v1/enseignant/liste-presence')->assertOk()
            ->assertJsonCount(2, 'seances.0.etudiants')->assertJsonCount(1, 'seances.1.etudiants');

        $data['seance']->update(['date_effective' => '2026-09-16']);
        $url = '/api/v1/enseignant/liste-presence/'.$data['seance']->id;
        $this->getJson($url)->assertOk()->assertJsonCount(2, 'feuille_presence.etudiants');
        $data['seance']->update(['date_effective' => null, 'statut' => 'prevue']);
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'feuille_presence.etudiants');
    }

    public function test_cours_commun_exclut_inscriptions_posterieures(): void
    {
        $data = $this->contexte();
        $data['seance']->update(['id_promotion' => null]);
        $data['horsPromotion']->inscriptions()->update(['date_inscription' => '2026-09-15']);
        $url = '/api/v1/enseignant/liste-presence/'.$data['seance']->id;
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'feuille_presence.etudiants')
            ->assertJsonPath('feuille_presence.etudiants.0.id', $data['etudiants'][0]->id);
        $this->getJson('/api/v1/enseignant/liste-presence')->assertOk()->assertJsonCount(1, 'seances.0.etudiants');
    }
}
