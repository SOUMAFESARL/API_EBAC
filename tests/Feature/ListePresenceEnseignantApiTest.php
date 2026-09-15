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

    public function test_affiche_uniquement_les_etudiants_de_la_promotion_concernee(): void
    {
        $data = $this->contexte();
        Sanctum::actingAs($data['enseignant']);
        $this->getJson('/api/v1/enseignant/liste-presence/'.$data['seance']->id)->assertOk()
            ->assertJsonCount(2, 'feuille_presence.etudiants')
            ->assertJsonMissing(['matricule' => $data['horsPromotion']->matricule]);
    }

    public function test_enregistre_et_valide_definitivement_la_presence_et_cree_le_cours_a_faire(): void
    {
        $data = $this->contexte();
        Sanctum::actingAs($data['enseignant']);
        $presences = [['id_etudiant' => $data['etudiants'][0]->id, 'statut' => 'present'], ['id_etudiant' => $data['etudiants'][1]->id, 'statut' => 'absent']];
        $url = '/api/v1/enseignant/liste-presence/'.$data['seance']->id;
        $this->putJson($url, compact('presences'))->assertOk()->assertJsonPath('feuille_presence.presence.absents', 1);
        $this->postJson($url.'/valider')->assertOk()->assertJsonPath('feuille_presence.modifiable', false);
        $this->assertDatabaseHas('cours_a_faire', ['id_etudiant' => $data['etudiants'][1]->id, 'id_seance' => $data['seance']->id, 'statut' => 'a_faire']);
        $this->putJson($url, compact('presences'))->assertUnprocessable()->assertJsonValidationErrors('presences');
        $this->postJson($url.'/valider')->assertUnprocessable()->assertJsonValidationErrors('presences');
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
}
