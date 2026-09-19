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

class NotesEnseignantApiTest extends TestCase
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

        \App\Models\AffectationEnseignant::create(['enseignant_id' => $enseignant->id, 'id_matiere' => $matiere->id, 'portee' => 'matiere', 'date_debut' => '2026-09-01']);
        return compact('admin', 'enseignant', 'autreEnseignant', 'seance', 'etudiants', 'horsPromotion', 'annee', 'promotion', 'cours');
    }


    private function url(array $data): string
    {
        return '/api/v1/enseignant/notes/'.$data['cours']->id.'?id_promotion='.$data['promotion']->id.'&id_annee_academique='.$data['annee']->id;
    }

    private function presences(array $data): void
    {
        $this->postJson('/api/v1/enseignant/liste-presence/'.$data['seance']->id.'/valider', [
            'presences' => [
                ['id_etudiant' => $data['etudiants'][0]->id, 'statut' => 'present'],
                ['id_etudiant' => $data['etudiants'][1]->id, 'statut' => 'absent'],
            ],
        ])->assertOk();
    }

    public function test_saisie_presence_moyenne_transmission_et_verrouillage(): void
    {
        $data = $this->contexte();
        $url = $this->url($data);
        $payload = ['notes' => [['id_etudiant' => $data['etudiants'][0]->id, 'note' => 15.5]]];
        $this->getJson($url)->assertOk()->assertJsonPath('feuille_notes.saisie_ouverte', false);
        $this->putJson($url, $payload)->assertUnprocessable();
        $this->presences($data);
        $this->getJson($url)->assertOk()->assertJsonPath('feuille_notes.saisie_ouverte', true)
            ->assertJsonCount(2, 'feuille_notes.etudiants')->assertJsonPath('feuille_notes.etudiants.1.evaluable', false);
        $this->putJson($url, $payload)->assertOk()->assertJsonPath('feuille_notes.etudiants.0.note', 15.5)
            ->assertJsonPath('feuille_notes.etudiants.0.moyenne_matiere', 15.5);
        $this->postJson(str_replace('?', '/transmettre?', $url))->assertOk()
            ->assertJsonPath('feuille_notes.statut', 'transmise')->assertJsonPath('feuille_notes.saisie_ouverte', false);
        $this->putJson($url, $payload)->assertUnprocessable();
        $this->assertDatabaseCount('notes_cours', 1);
    }

    public function test_refuse_absents_etrangers_notes_invalides_et_transmission_incomplete(): void
    {
        $data = $this->contexte();
        $this->presences($data);
        $url = $this->url($data);
        foreach ([
            [['id_etudiant' => $data['etudiants'][1]->id, 'note' => 10]],
            [['id_etudiant' => $data['horsPromotion']->id, 'note' => 10]],
            [['id_etudiant' => $data['etudiants'][0]->id, 'note' => 21]],
            [['id_etudiant' => $data['etudiants'][0]->id, 'note' => -1]],
            [['id_etudiant' => $data['etudiants'][0]->id, 'note' => 10], ['id_etudiant' => $data['etudiants'][0]->id, 'note' => 11]],
        ] as $notes) {
            $this->putJson($url, compact('notes'))->assertUnprocessable();
        }
        $this->postJson(str_replace('?', '/transmettre?', $url))->assertUnprocessable();
        $this->assertDatabaseCount('notes_cours', 0);
        $this->putJson($url, ['notes' => [['id_etudiant' => $data['etudiants'][0]->id, 'note' => 0]]])->assertOk()
            ->assertJsonPath('feuille_notes.notes_manquantes', 0);
        $this->putJson($url, ['notes' => [['id_etudiant' => $data['etudiants'][0]->id, 'note' => null]]])->assertOk()
            ->assertJsonPath('feuille_notes.notes_manquantes', 1);
    }

    public function test_acces_limite_a_affectation_et_promotion(): void
    {
        $this->getJson('/api/v1/enseignant/notes')->assertUnauthorized();
        $data = $this->contexte();
        $this->getJson('/api/v1/enseignant/notes?id_annee_academique='.$data['annee']->id)->assertOk()
            ->assertJsonCount(1, 'enseignements');
        $autre = Promotion::whereKeyNot($data['promotion']->id)->firstOrFail();
        $this->getJson(str_replace('id_promotion='.$data['promotion']->id, 'id_promotion='.$autre->id, $this->url($data)))->assertNotFound();
        Sanctum::actingAs($data['autreEnseignant']);
        $this->getJson($this->url($data))->assertNotFound();
        $this->putJson($this->url($data), ['notes' => []])->assertNotFound();
        Sanctum::actingAs($data['admin']);
        $this->getJson($this->url($data))->assertForbidden();
    }

    public function test_moyenne_ponderee_isolee_par_annee_et_affectation_expiree(): void
    {
        $data = $this->contexte();
        $this->presences($data);
        $autreCours = Cours::create(['id_module' => $data['cours']->id_module, 'libelle' => 'Autre cours', 'ordre' => 2, 'coefficient' => 3]);
        $feuille = \App\Models\FeuilleNotes::create(['id_annee_academique' => $data['annee']->id,
            'id_promotion' => $data['promotion']->id, 'id_cours' => $autreCours->id, 'updated_by' => $data['enseignant']->id]);
        $feuille->notes()->create(['id_etudiant' => $data['etudiants'][0]->id, 'note' => 10]);
        $ancienne = AnneeAcademique::whereKeyNot($data['annee']->id)->firstOrFail();
        $feuille = \App\Models\FeuilleNotes::create(['id_annee_academique' => $ancienne->id,
            'id_promotion' => $data['promotion']->id, 'id_cours' => $autreCours->id, 'updated_by' => $data['enseignant']->id]);
        $feuille->notes()->create(['id_etudiant' => $data['etudiants'][0]->id, 'note' => 20]);
        $this->putJson($this->url($data), ['notes' => [['id_etudiant' => $data['etudiants'][0]->id, 'note' => 18]]])
            ->assertOk()->assertJsonPath('feuille_notes.etudiants.0.moyenne_matiere', 12);
        \App\Models\AffectationEnseignant::where('enseignant_id', $data['enseignant']->id)->update(['date_fin' => '2026-09-14']);
        $this->getJson($this->url($data))->assertNotFound();
    }

    public function test_aucune_seance_ou_seance_supplementaire_sans_presence_bloquent(): void
    {
        $data = $this->contexte();
        $data['seance']->update(['statut' => 'prevue']);
        $this->getJson($this->url($data))->assertOk()->assertJsonPath('feuille_notes.saisie_ouverte', false);
        $data['seance']->update(['statut' => 'realisee']);
        $this->presences($data);
        $autre = $data['seance']->replicate();
        $autre->date_prevue = '2026-09-21';
        $autre->save();
        $this->getJson($this->url($data))->assertOk()->assertJsonPath('feuille_notes.saisie_ouverte', false)
            ->assertJsonPath('feuille_notes.seances_realisees', 2);
    }
}
