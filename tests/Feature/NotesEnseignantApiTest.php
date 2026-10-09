<?php

namespace Tests\Feature;

use App\Models\AffectationEnseignant;
use App\Models\AnneeAcademique;
use App\Models\Cours;
use App\Models\Creneau;
use App\Models\Etudiant;
use App\Models\FeuilleNotes;
use App\Models\Inscription;
use App\Models\Matiere;
use App\Models\Module;
use App\Models\Niveau;
use App\Models\NoteCours;
use App\Models\Presence;
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

        AffectationEnseignant::create(['enseignant_id' => $enseignant->id, 'id_matiere' => $matiere->id, 'portee' => 'matiere', 'date_debut' => '2026-09-01']);

        return compact('admin', 'enseignant', 'autreEnseignant', 'seance', 'etudiants', 'horsPromotion', 'annee', 'promotion', 'cours');
    }

    public function test_notes_par_matiere_sans_cours(): void
    {
        $data = $this->contexte();
        $matiere = $data['cours']->module->id_matiere;
        $data['seance']->update(['id_cours' => null]);
        $data['seance']->creneau->update(['id_cours' => null]);
        $contexte = ['id_seance' => $data['seance']->id, 'id_matiere' => $matiere, 'id_promotion' => $data['promotion']->id,
            'id_annee_academique' => $data['annee']->id];
        $base = '/api/v1/enseignant/notes';
        $url = $base.'/feuille?'.http_build_query($contexte);
        $payload = [...$contexte, 'notes' => [['id_etudiant' => $data['etudiants'][0]->id, 'note' => 15.5]]];
        $this->putJson($base, $payload)->assertUnprocessable();
        $this->presences($data);
        $this->getJson($url)->assertOk()->assertJsonPath('feuille_notes.cours', null)
            ->assertJsonPath('feuille_notes.matiere.id', $matiere)->assertJsonPath('feuille_notes.saisie_ouverte', true);
        $this->putJson($base, $payload)->assertOk()->assertJsonPath('feuille_notes.etudiants.0.notes.0.note', 15.5)
            ->assertJsonPath('feuille_notes.etudiants.0.moyenne_matiere', null);
        $this->putJson($base, [...$contexte, 'notes' => [['id_etudiant' => $data['etudiants'][1]->id, 'note' => 10]]])->assertUnprocessable();
        $this->putJson($base, [...$contexte, 'notes' => [['id_etudiant' => $data['etudiants'][0]->id, 'note' => null]]])->assertUnprocessable();
        $this->postJson($base.'/transmettre', $contexte)->assertOk()->assertJsonPath('feuille_notes.statut', 'transmise');
        $this->postJson($base.'/transmettre', $payload)->assertUnprocessable();
        $this->putJson($base, $payload)->assertUnprocessable();
        $this->assertDatabaseHas('feuilles_notes', [...$contexte, 'id_cours' => null, 'statut' => 'transmise']);
        $this->assertDatabaseCount('feuilles_notes', 1);
        Sanctum::actingAs($data['autreEnseignant']);
        $this->getJson($url)->assertNotFound();
    }

    public function test_matiere_requise_et_affectation_cours_insuffisante(): void
    {
        $data = $this->contexte();
        $contexte = ['id_seance' => $data['seance']->id, 'id_promotion' => $data['promotion']->id, 'id_annee_academique' => $data['annee']->id];
        $this->getJson('/api/v1/enseignant/notes/feuille?'.http_build_query($contexte))
            ->assertUnprocessable()->assertJsonValidationErrors('id_matiere');
        AffectationEnseignant::where('enseignant_id', $data['enseignant']->id)
            ->update(['portee' => 'cours', 'id_cours' => $data['cours']->id]);
        $contexte['id_matiere'] = $data['cours']->module->id_matiere;
        $this->getJson('/api/v1/enseignant/notes/feuille?'.http_build_query($contexte))->assertNotFound();
    }

    public function test_feuilles_matiere_et_cours_distinctes_et_presences_de_toute_la_matiere(): void
    {
        $data = $this->contexte();
        $this->presences($data);
        $notes = ['notes' => [['id_etudiant' => $data['etudiants'][0]->id, 'note' => 12]]];
        $this->putJson($this->url($data), $notes)->assertOk();
        $contexte = ['id_seance' => $data['seance']->id, 'id_matiere' => $data['cours']->module->id_matiere,
            'id_promotion' => $data['promotion']->id, 'id_annee_academique' => $data['annee']->id];
        $this->putJson('/api/v1/enseignant/notes', [...$contexte,
            'notes' => [['id_etudiant' => $data['etudiants'][0]->id, 'note' => 17]],
        ])->assertOk()->assertJsonPath('feuille_notes.etudiants.0.moyenne_matiere', null);
        $this->assertDatabaseCount('feuilles_notes', 2);
        $this->getJson($this->url($data))->assertOk()->assertJsonPath('feuille_notes.etudiants.0.notes.0.note', 12)
            ->assertJsonPath('feuille_notes.etudiants.0.moyenne_matiere', null);
        $autre = $data['seance']->replicate();
        $autre->date_prevue = '2026-09-21';
        $autre->id_cours = null;
        $autre->save();
        $this->getJson('/api/v1/enseignant/notes/feuille?'.http_build_query($contexte))->assertOk()
            ->assertJsonPath('feuille_notes.saisie_ouverte', false)->assertJsonPath('feuille_notes.seances_realisees', 1);
        $this->postJson('/api/v1/enseignant/notes/transmettre', $contexte)->assertOk()
            ->assertJsonCount(1, 'feuille_notes.historique');
    }

    private function url(array $data): string
    {
        return '/api/v1/enseignant/notes/'.$data['cours']->id.'?id_seance='.$data['seance']->id.'&id_promotion='.$data['promotion']->id.'&id_annee_academique='.$data['annee']->id;
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

    public function test_absent_evaluable_uniquement_apres_autorisation_de_toutes_ses_absences(): void
    {
        $data = $this->contexte();
        $this->presences($data);
        $autre = $data['seance']->replicate();
        $autre->date_prevue = '2026-09-21';
        $autre->save();
        $this->presences([...$data, 'seance' => $autre]);
        $absences = Presence::where('id_etudiant', $data['etudiants'][1]->id)->get();
        $base = '/api/v1/administration/autorisations-evaluations';
        $payload = ['notes' => [['id_etudiant' => $data['etudiants'][1]->id, 'note' => 13]]];
        $this->putJson($this->url($data), $payload)->assertUnprocessable();
        $this->postJson($base.'/'.$absences[0]->id.'/autoriser', ['motif' => 'Justificatif accepté'])->assertForbidden();
        Sanctum::actingAs($data['admin']);
        $this->getJson($base.'?autorisee=0')->assertOk()->assertJsonPath('total', 2);
        $this->postJson($base.'/'.$absences[0]->id.'/autoriser', ['motif' => ' '])->assertUnprocessable();
        $this->postJson($base.'/'.$absences[0]->id.'/autoriser', ['motif' => 'Justificatif accepté'])->assertOk()
            ->assertJsonPath('absence.evaluation_autorisee_par', $data['admin']->id);
        Sanctum::actingAs($data['enseignant']);
        $this->putJson($this->url($data), $payload)->assertUnprocessable();
        Sanctum::actingAs($data['admin']);
        $this->postJson($base.'/'.$absences[1]->id.'/autoriser', ['motif' => 'Justificatif accepté'])->assertOk();
        $date = $absences[1]->fresh()->evaluation_autorisee_le->toISOString();
        $this->postJson($base.'/'.$absences[1]->id.'/autoriser', ['motif' => 'Autre motif'])->assertOk()
            ->assertJsonPath('absence.evaluation_autorisee_le', $date)
            ->assertJsonPath('absence.motif_autorisation_evaluation', 'Justificatif accepté');
        $present = Presence::where('statut', 'present')->firstOrFail();
        $this->postJson($base.'/'.$present->id.'/autoriser', ['motif' => 'Test'])->assertUnprocessable();
        $this->getJson($base.'/'.$absences[0]->id)->assertOk();
        $this->postJson($base.'/999999/autoriser', ['motif' => 'Test'])->assertNotFound();
        Sanctum::actingAs($data['enseignant']);
        $payload['notes'][] = ['id_etudiant' => $data['etudiants'][0]->id, 'note' => 15];
        $this->putJson($this->url($data), $payload)->assertOk()
            ->assertJsonPath('feuille_notes.etudiants.1.evaluable', true)
            ->assertJsonPath('feuille_notes.etudiants.1.statut_presence', 'absent')
            ->assertJsonPath('feuille_notes.etudiants.1.evaluation_autorisee', true);
        $contexte = ['id_seance' => $data['seance']->id, 'id_matiere' => $data['cours']->module->id_matiere,
            'id_promotion' => $data['promotion']->id, 'id_annee_academique' => $data['annee']->id];
        $this->putJson('/api/v1/enseignant/notes', [...$contexte, ...$payload])->assertOk();
        $this->postJson(str_replace('?', '/transmettre?', $this->url($data)))->assertOk()->assertJsonPath('feuille_notes.statut', 'transmise');
        $this->assertDatabaseHas('presences', ['id' => $absences[0]->id, 'statut' => 'absent']);
    }

    public function test_autorisation_evaluation_roles_et_presence_non_validee(): void
    {
        $data = $this->contexte();
        $feuille = $data['seance']->feuillePresence()->create(['statut' => 'brouillon']);
        $absence = $feuille->presences()->create(['id_etudiant' => $data['etudiants'][1]->id, 'statut' => 'absent']);
        $base = '/api/v1/administration/autorisations-evaluations';
        Sanctum::actingAs($data['admin']);
        $this->postJson($base.'/'.$absence->id.'/autoriser', ['motif' => 'Test'])->assertUnprocessable();
        $feuille->update(['statut' => 'validee']);
        foreach (['DIRECTION', 'SECRETARIAT', 'SECRETAIRE_ACADEMIQUE'] as $code) {
            $role = Role::create(['code' => $code, 'libelle' => $code]);
            Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));
            $this->postJson($base.'/'.$absence->id.'/autoriser', ['motif' => 'Test'])->assertOk();
        }
        foreach (['ETUDIANT', 'GESTIONNAIRE'] as $code) {
            $role = Role::create(['code' => $code, 'libelle' => $code]);
            Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));
            $this->getJson($base)->assertForbidden();
            $this->postJson($base.'/'.$absence->id.'/autoriser', ['motif' => 'Test'])->assertForbidden();
        }
        $data['admin']->update(['is_active' => false]);
        Sanctum::actingAs($data['admin']);
        $this->postJson($base.'/'.$absence->id.'/autoriser', ['motif' => 'Test'])->assertForbidden();
    }

    public function test_demande_correction_verifie_et_retourne_la_seance(): void
    {
        $data = $this->contexte();
        $this->presences($data);
        $this->postJson(str_replace('?', '/transmettre?', $this->url($data)), [
            'notes' => [['id_etudiant' => $data['etudiants'][0]->id, 'note' => 12]],
        ])->assertOk();
        $note = NoteCours::firstOrFail();
        $autre = $data['seance']->replicate();
        $autre->date_prevue = '2026-09-15';
        $autre->save();
        $base = '/api/v1/enseignant/corrections-notes';
        $payload = ['id_note' => $note->id, 'note_proposee' => 16,
            'motif' => 'Erreur de saisie', 'id_seance' => $data['seance']->id];
        $sansSeance = $payload;
        unset($sansSeance['id_seance']);
        foreach ([$sansSeance, [...$payload, 'id_seance' => null]] as $invalide) {
            $this->postJson($base, $invalide)->assertUnprocessable()->assertJsonValidationErrors('id_seance');
        }
        $this->postJson($base, [...$payload, 'id_seance' => 999999])->assertUnprocessable()
            ->assertJsonValidationErrors('id_seance');
        $this->postJson($base, [...$payload, 'id_seance' => $autre->id])->assertUnprocessable()
            ->assertJsonValidationErrors('id_seance');
        $this->assertDatabaseCount('corrections_notes', 0);
        $id = $this->postJson($base, $payload)->assertCreated()
            ->assertJsonPath('correction.id_seance', $data['seance']->id)->json('correction.id');
        $this->getJson($base.'/'.$id)->assertOk()
            ->assertJsonPath('correction.id_seance', $data['seance']->id);
        $this->getJson($base)->assertOk()->assertJsonPath('data.0.id_seance', $data['seance']->id);
    }

    public function test_demande_groupee_atomique_et_validation_des_notes(): void
    {
        $data = $this->contexte();
        $this->presences($data);
        $this->postJson(str_replace('?', '/transmettre?', $this->url($data)), [
            'notes' => [['id_etudiant' => $data['etudiants'][0]->id, 'note' => 12]],
        ])->assertOk();
        $this->postJson('/api/v1/enseignant/notes/transmettre', [
            'id_seance' => $data['seance']->id,
            'id_matiere' => $data['cours']->module->id_matiere, 'id_promotion' => $data['promotion']->id,
            'id_annee_academique' => $data['annee']->id,
            'notes' => [['id_etudiant' => $data['etudiants'][0]->id, 'note' => 10]],
        ])->assertOk();
        $notes = NoteCours::orderBy('id')->get();
        $base = '/api/v1/enseignant/corrections-notes';
        $payload = ['id_seance' => $data['seance']->id, 'motif' => 'Erreur de report des notes', 'notes' => [
            ['id_note' => $notes[0]->id, 'note_proposee' => 15],
            ['id_note' => $notes[1]->id, 'note_proposee' => 16],
        ]];
        $sansSeance = $payload;
        unset($sansSeance['id_seance']);
        $this->postJson($base, $sansSeance)->assertUnprocessable()->assertJsonValidationErrors('id_seance');
        $this->postJson($base, [...$payload, 'notes' => []])->assertUnprocessable();
        $this->postJson($base, [...$payload, 'motif' => ' '])->assertUnprocessable();
        $this->postJson($base, [...$payload, 'id_note' => $notes[0]->id])->assertUnprocessable()
            ->assertJsonValidationErrors('id_note');
        $this->postJson($base, [...$payload, 'notes' => array_fill(0, 101, $payload['notes'][0])])->assertUnprocessable()
            ->assertJsonValidationErrors('notes');
        $this->postJson($base, [...$payload, 'notes' => [
            ['id_note' => $notes[0]->id, 'note_proposee' => 21],
        ]])->assertUnprocessable()->assertJsonValidationErrors('notes.0.note_proposee');
        $this->postJson($base, [...$payload, 'notes' => [$payload['notes'][0], $payload['notes'][0]]])->assertUnprocessable();
        $this->postJson($base, [...$payload, 'notes' => [$payload['notes'][0],
            ['id_note' => $notes[1]->id, 'note_proposee' => 10],
        ]])->assertUnprocessable();
        $this->assertDatabaseCount('corrections_notes', 0);
        $this->assertDatabaseCount('traces_corrections_notes', 0);
        $this->postJson($base, [...$payload, 'notes' => [$payload['notes'][0],
            ['id_note' => 999999, 'note_proposee' => 14],
        ]])->assertNotFound();
        $this->assertDatabaseCount('corrections_notes', 0);
        $autre = $data['seance']->replicate();
        $autre->date_prevue = '2026-09-15';
        $autre->save();
        $feuille = $notes[1]->feuilleNotes;
        $feuille->update(['id_seance' => $autre->id]);
        $this->postJson($base, $payload)->assertUnprocessable()->assertJsonValidationErrors('id_seance');
        $this->assertDatabaseCount('corrections_notes', 0);
        $this->assertDatabaseCount('traces_corrections_notes', 0);
        $feuille->update(['id_seance' => $data['seance']->id]);
        $response = $this->postJson($base, $payload)->assertCreated()->assertJsonPath('nombre_demandes', 2)
            ->assertJsonCount(2, 'corrections')->assertJsonPath('corrections.0.statut', 'en_attente')
            ->assertJsonPath('corrections.0.id_seance', $data['seance']->id)
            ->assertJsonPath('corrections.1.id_seance', $data['seance']->id);
        $this->assertEquals(12, $notes[0]->fresh()->note);
        $this->assertEquals(10, $notes[1]->fresh()->note);
        $this->postJson($base, $payload)->assertUnprocessable();
        $this->assertDatabaseCount('corrections_notes', 2);
        foreach ($response->json('corrections') as $correction) {
            $id = $correction['id'];
            $this->postJson($base.'/'.$id.'/appliquer')->assertUnprocessable();
            Sanctum::actingAs($data['admin']);
            $this->postJson('/api/v1/administration/corrections-notes/'.$id.'/autoriser')->assertOk();
            Sanctum::actingAs($data['enseignant']);
            $this->postJson($base.'/'.$id.'/appliquer')->assertOk();
        }
        $this->assertEquals(15, $notes[0]->fresh()->note);
        $this->assertEquals(16, $notes[1]->fresh()->note);
        $this->assertDatabaseCount('traces_corrections_notes', 6);
    }

    public function test_correction_refuse_affectation_expiree_et_note_changee(): void
    {
        $data = $this->contexte();
        $this->presences($data);
        $this->postJson(str_replace('?', '/transmettre?', $this->url($data)), [
            'notes' => [['id_etudiant' => $data['etudiants'][0]->id, 'note' => 12]],
        ])->assertOk();
        $note = NoteCours::firstOrFail();
        $base = '/api/v1/enseignant/corrections-notes';
        $id = $this->postJson($base, ['id_seance' => $data['seance']->id, 'id_note' => $note->id, 'note_proposee' => 16, 'motif' => 'Erreur'])->assertCreated()->json('correction.id');
        Sanctum::actingAs($data['admin']);
        $this->postJson('/api/v1/administration/corrections-notes/'.$id.'/autoriser')->assertOk();
        Sanctum::actingAs($data['enseignant']);
        AffectationEnseignant::where('enseignant_id', $data['enseignant']->id)->update(['date_fin' => '2026-09-14']);
        $this->postJson($base.'/'.$id.'/appliquer')->assertNotFound();
        $this->assertEquals(12, $note->fresh()->note);
        AffectationEnseignant::where('enseignant_id', $data['enseignant']->id)->update(['date_fin' => null]);
        $note->update(['note' => 13]);
        $this->postJson($base.'/'.$id.'/appliquer')->assertUnprocessable();
        $this->assertEquals(13, $note->fresh()->note);
        $this->assertDatabaseHas('corrections_notes', ['id' => $id, 'statut' => 'autorisee', 'note_finale' => null]);
        $this->assertDatabaseCount('traces_corrections_notes', 2);
    }

    public function test_correction_enseignant_apres_validation_secretariat(): void
    {
        $data = $this->contexte();
        $this->presences($data);
        $url = $this->url($data);
        $this->putJson($url, ['notes' => [['id_etudiant' => $data['etudiants'][0]->id, 'note' => 12]]])->assertOk();
        $note = NoteCours::firstOrFail();
        $base = '/api/v1/enseignant/corrections-notes';
        $payload = ['id_seance' => $data['seance']->id, 'id_note' => $note->id, 'note_proposee' => 16, 'motif' => 'Erreur sur la copie'];
        FeuilleNotes::firstOrFail()->update(['statut' => 'brouillon']);
        $this->postJson($base, $payload)->assertUnprocessable();
        $this->postJson(str_replace('?', '/transmettre?', $url))->assertOk();
        $this->postJson($base, [...$payload, 'motif' => ' '])->assertUnprocessable();
        $id = $this->postJson($base, $payload)->assertCreated()->json('correction.id');
        $this->postJson($base, $payload)->assertUnprocessable();
        $this->postJson($base.'/'.$id.'/appliquer')->assertUnprocessable();
        $this->assertEquals(12, $note->fresh()->note);
        Sanctum::actingAs($data['autreEnseignant']);
        $this->getJson($base)->assertOk()->assertJsonCount(0, 'data');
        $this->getJson($base.'/'.$id)->assertNotFound();
        $this->postJson($base, $payload)->assertNotFound();
        $this->postJson($base.'/'.$id.'/appliquer')->assertNotFound();
        $role = Role::create(['code' => 'SECRETARIAT', 'libelle' => 'Secretariat']);
        $secretariat = User::factory()->create(['id_role' => $role->id]);
        Sanctum::actingAs($secretariat);
        $admin = '/api/v1/administration/corrections-notes';
        $this->postJson($admin.'/'.$id.'/autoriser')->assertOk();
        $this->assertEquals(12, $note->fresh()->note);
        Sanctum::actingAs($data['enseignant']);
        $this->putJson($url, ['notes' => [['id_etudiant' => $data['etudiants'][0]->id, 'note' => 16]]])->assertUnprocessable();
        $this->postJson($base.'/'.$id.'/appliquer')->assertOk()->assertJsonPath('correction.note_finale', 16);
        $this->postJson($base.'/'.$id.'/appliquer')->assertUnprocessable();
        $this->getJson($base.'/'.$id)->assertOk()->assertJsonCount(3, 'historique');
        $this->assertDatabaseHas('corrections_notes', ['id' => $id, 'demande_par' => $data['enseignant']->id,
            'autorisee_par' => $secretariat->id, 'appliquee_par' => $data['enseignant']->id]);
        $id = $this->postJson($base, [...$payload, 'note_proposee' => 15])->assertCreated()->json('correction.id');
        Sanctum::actingAs($secretariat);
        $this->postJson($admin.'/'.$id.'/rejeter', ['motif' => 'Copie non justifiee'])->assertOk();
        Sanctum::actingAs($data['enseignant']);
        $this->postJson($base.'/'.$id.'/appliquer')->assertUnprocessable();
    }

    public function test_correction_enseignant_par_matiere_validee_par_admin(): void
    {
        $data = $this->contexte();
        $this->presences($data);
        $contexte = ['id_seance' => $data['seance']->id, 'id_matiere' => $data['cours']->module->id_matiere,
            'id_promotion' => $data['promotion']->id, 'id_annee_academique' => $data['annee']->id];
        $this->postJson('/api/v1/enseignant/notes/transmettre', [...$contexte,
            'notes' => [['id_etudiant' => $data['etudiants'][0]->id, 'note' => 10]],
        ])->assertOk();
        $note = NoteCours::firstOrFail();
        $base = '/api/v1/enseignant/corrections-notes';
        $id = $this->postJson($base, ['id_seance' => $data['seance']->id, 'id_note' => $note->id, 'note_proposee' => 14, 'motif' => 'Erreur de saisie'])->assertCreated()->json('correction.id');
        $this->postJson('/api/v1/administration/corrections-notes/'.$id.'/autoriser')->assertForbidden();
        Sanctum::actingAs($data['admin']);
        $this->postJson('/api/v1/administration/corrections-notes/'.$id.'/autoriser')->assertOk();
        Sanctum::actingAs($data['enseignant']);
        $this->postJson($base.'/'.$id.'/appliquer')->assertOk()->assertJsonPath('correction.note_finale', 14);
    }

    public function test_administration_recoit_les_notes_apres_transmission(): void
    {
        $data = $this->contexte();
        $this->presences($data);
        $base = '/api/v1/administration/notes-transmises';
        $url = $this->url($data);
        $this->putJson($url, ['notes' => [['id_etudiant' => $data['etudiants'][0]->id, 'note' => 14]]])->assertOk();
        $id = FeuilleNotes::firstOrFail()->id;
        $this->getJson($base)->assertForbidden();
        Sanctum::actingAs($data['admin']);
        $this->getJson($base)->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson($base.'/'.$id)->assertOk();
        Sanctum::actingAs($data['enseignant']);
        $this->postJson(str_replace('?', '/transmettre?', $url))->assertOk();
        Sanctum::actingAs($data['admin']);
        $this->getJson($base.'?id_matiere='.$data['cours']->module->id_matiere)->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('feuilles_notes.0.nombre_notes', 1);
        $this->getJson($base.'/'.$id)->assertOk()->assertJsonPath('feuille_notes.notes.0.note', 14)
            ->assertJsonPath('feuille_notes.notes.0.etudiant.id', $data['etudiants'][0]->id);
        $this->getJson($base.'?per_page=101')->assertUnprocessable();
        Sanctum::actingAs($data['enseignant']);
        $contexte = ['id_seance' => $data['seance']->id, 'id_matiere' => $data['cours']->module->id_matiere,
            'id_promotion' => $data['promotion']->id, 'id_annee_academique' => $data['annee']->id];
        $this->postJson('/api/v1/enseignant/notes/transmettre', [...$contexte,
            'notes' => [['id_etudiant' => $data['etudiants'][0]->id, 'note' => 15]],
        ])->assertOk();
        Sanctum::actingAs($data['admin']);
        $this->getJson($base.'?id_matiere='.$contexte['id_matiere'])->assertOk()->assertJsonPath('meta.total', 2);
        $id = FeuilleNotes::whereNull('id_cours')->firstOrFail()->id;
        $this->getJson($base.'/'.$id)->assertOk()->assertJsonPath('feuille_notes.cours', null)
            ->assertJsonPath('feuille_notes.matiere.id', $contexte['id_matiere']);
    }

    public function test_refus_secretariat_rouvre_saisie_et_permet_retransmission(): void
    {
        $data = $this->contexte();
        $this->presences($data);
        $url = $this->url($data);
        $transmission = str_replace('?', '/transmettre?', $url);
        $this->postJson($transmission, ['notes' => [['id_etudiant' => $data['etudiants'][0]->id, 'note' => 14]]])->assertOk();
        $id = FeuilleNotes::firstOrFail()->id;
        $this->getJson('/api/v1/enseignant/transmissions-notes/'.$id)->assertOk()
            ->assertJsonPath('transmission.transmise_par', $data['enseignant']->id);
        Sanctum::actingAs($data['admin']);
        $this->postJson('/api/v1/administration/notes-transmises/'.$id.'/rejeter-secretariat', ['motif' => 'Verifier la copie'])->assertOk();
        Sanctum::actingAs($data['enseignant']);
        $this->getJson($url)->assertOk()->assertJsonPath('feuille_notes.saisie_ouverte', true)
            ->assertJsonPath('feuille_notes.historique.1.motif', 'Verifier la copie');
        $this->putJson($url, ['notes' => [['id_etudiant' => $data['etudiants'][0]->id, 'note' => 16]]])->assertOk();
        $this->postJson($transmission)->assertOk()->assertJsonPath('feuille_notes.statut', 'transmise')
            ->assertJsonPath('feuille_notes.saisie_ouverte', false)->assertJsonCount(3, 'feuille_notes.historique');
        Sanctum::actingAs($data['admin']);
        $this->postJson('/api/v1/administration/notes-transmises/'.$id.'/valider-secretariat')->assertOk();
        Sanctum::actingAs($data['enseignant']);
        $this->putJson($url, ['notes' => [['id_etudiant' => $data['etudiants'][0]->id, 'note' => 18]]])->assertUnprocessable();
    }

    public function test_route_explicite_matiere_corrige_et_retransmet_la_meme_feuille(): void
    {
        $data = $this->contexte();
        $this->presences($data);
        $matiere = $data['cours']->module->id_matiere;
        $url = '/api/v1/enseignant/notes/matieres/'.$matiere;
        $payload = ['id_seance' => $data['seance']->id, 'id_promotion' => $data['promotion']->id,
            'id_annee_academique' => $data['annee']->id,
            'notes' => [['id_etudiant' => $data['etudiants'][0]->id, 'note' => 12]]];
        $this->putJson($url, $payload)->assertOk()->assertJsonPath('feuille_notes.matiere.id', $matiere);
        $feuille = FeuilleNotes::firstOrFail();
        $feuille->changerStatut('rejetee_direction', $data['admin']->id, 'rejet_direction', 'Erreur');
        $payload['notes'][0]['note'] = 16;
        $this->putJson($url, $payload)->assertOk()->assertJsonPath('feuille_notes.statut_workflow', 'transmise');
        $this->assertDatabaseCount('feuilles_notes', 1);
        $this->assertSame(16.0, $feuille->notes()->first()->note);
        unset($payload['notes']);
        $this->getJson($url.'?'.http_build_query($payload))->assertOk()->assertJsonPath('feuille_notes.matiere.id', $matiere);
        $this->postJson($url.'/transmettre', $payload)->assertOk();
    }

    public function test_decisions_direction_moyenne_et_reouverture_feuille_rejetee(): void
    {
        $data = $this->contexte();
        $this->presences($data);
        $url = $this->url($data);
        $this->putJson($url, ['notes' => [['id_etudiant' => $data['etudiants'][0]->id, 'note' => 14]]])->assertOk()
            ->assertJsonPath('feuille_notes.etudiants.0.moyenne_matiere', null);
        $feuille = FeuilleNotes::firstOrFail();
        $base = '/api/v1/administration/notes-transmises/'.$feuille->id;
        $role = Role::create(['code' => 'SECRETAIRE_ACADEMIQUE', 'libelle' => 'Secretariat']);
        $secretaire = User::factory()->create(['id_role' => $role->id]);
        $autre = $feuille->replicate();
        $autre->id_seance = null;
        $autre->save();
        $autre->notes()->create(['id_etudiant' => $data['etudiants'][0]->id, 'note' => 20]);
        Sanctum::actingAs($data['admin']);
        $this->postJson($base.'/transmettre-direction')->assertOk();
        $this->postJson($base.'/rejeter-direction', ['motif' => 'Erreur'])->assertOk();
        Sanctum::actingAs($secretaire);
        $this->getJson($base)->assertOk()->assertJsonPath('feuille_notes.statut_workflow', 'rejetee_direction');
        $this->assertSame('transmise', $autre->fresh()->statut);
        Sanctum::actingAs($data['enseignant']);
        $this->getJson($url)->assertOk()->assertJsonPath('feuille_notes.statut_workflow', 'rejetee_direction')
            ->assertJsonPath('feuille_notes.saisie_ouverte', true);
        $this->putJson($url, ['notes' => [['id_etudiant' => $data['etudiants'][0]->id, 'note' => 16]]])->assertOk()
            ->assertJsonPath('feuille_notes.statut_workflow', 'transmise')->assertJsonPath('feuille_notes.saisie_ouverte', false);
        Sanctum::actingAs($data['admin']);
        $this->postJson($base.'/transmettre-direction')->assertOk();
        $this->postJson($base.'/valider-direction')->assertOk();
        Sanctum::actingAs($secretaire);
        $this->getJson($base)->assertOk()->assertJsonPath('feuille_notes.statut_workflow', 'validee_direction');
        Sanctum::actingAs($data['enseignant']);
        $this->getJson($url)->assertOk()->assertJsonPath('feuille_notes.statut_workflow', 'validee_direction')
            ->assertJsonPath('feuille_notes.etudiants.0.moyenne_matiere', 16)
            ->assertJsonPath('feuille_notes.etudiants.0.statut_moyenne', 'validee')
            ->assertJsonPath('feuille_notes.saisie_ouverte', false);
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
        $this->putJson($url, $payload)->assertOk()->assertJsonPath('feuille_notes.etudiants.0.notes.0.note', 15.5)
            ->assertJsonPath('feuille_notes.etudiants.0.moyenne_matiere', null);
        $this->postJson(str_replace('?', '/transmettre?', $url))->assertOk()
            ->assertJsonPath('feuille_notes.statut', 'transmise')->assertJsonPath('feuille_notes.saisie_ouverte', false);
        $this->putJson($url, $payload)->assertUnprocessable();
        $this->assertDatabaseCount('notes_cours', 1);
    }

    public function test_plusieurs_evaluations_matiere_transmises_ensemble_et_verrouillees(): void
    {
        $data = $this->contexte();
        $this->presences($data);
        $contexte = ['id_seance' => $data['seance']->id, 'id_matiere' => $data['cours']->module->id_matiere,
            'id_promotion' => $data['promotion']->id, 'id_annee_academique' => $data['annee']->id];
        $payload = fn ($evaluation, $note) => [...$contexte, 'evaluation' => $evaluation,
            'notes' => [['id_etudiant' => $data['etudiants'][0]->id, 'note' => $note]]];
        $url = '/api/v1/enseignant/notes';
        $this->putJson($url, [...$contexte, 'notes' => [
            ['id_etudiant' => $data['etudiants'][0]->id, 'evaluation' => 'devoir_1', 'note' => 10],
            ['id_etudiant' => $data['etudiants'][0]->id, 'evaluation' => 'examen', 'note' => 18],
        ]])->assertOk()
            ->assertJsonCount(2, 'feuille_notes.etudiants.0.notes')
            ->assertJsonMissingPath('feuille_notes.etudiants.0.note')
            ->assertJsonPath('feuille_notes.etudiants.0.moyenne_matiere', null)
            ->assertJsonPath('feuille_notes.statut', 'transmise')
            ->assertJsonPath('feuille_notes.saisie_ouverte', false);
        $this->postJson($url.'/transmettre', $contexte)->assertOk()
            ->assertJsonPath('feuille_notes.statut', 'transmise');
        $this->putJson($url, $payload('devoir_2', 20))->assertUnprocessable();
        $this->assertDatabaseCount('notes_cours', 2);
        Sanctum::actingAs($data['admin']);
        $this->getJson('/api/v1/administration/notes-transmises')->assertOk()
            ->assertJsonCount(2, 'feuilles_notes.0.notes')
            ->assertJsonPath('feuilles_notes.0.notes.0.evaluation', 'devoir_1');
    }

    public function test_transmission_directe_incomplete_annule_toutes_les_notes(): void
    {
        $data = $this->contexte();
        $this->presences($data);
        Presence::where('id_etudiant', $data['etudiants'][1]->id)->update(['statut' => 'present']);
        $this->putJson($this->url($data), ['notes' => [
            ['id_etudiant' => $data['etudiants'][0]->id, 'note' => 15],
        ]])->assertUnprocessable()->assertJsonValidationErrors('notes');
        $this->assertDatabaseCount('notes_cours', 0);
        $this->assertDatabaseCount('feuilles_notes', 0);
        $this->assertDatabaseCount('historique_feuilles_notes', 0);
        $this->getJson($this->url($data))->assertOk()->assertJsonPath('feuille_notes.statut', 'non_transmise');
    }

    public function test_tableau_notes_matiere_sans_enveloppe_et_acces_controle(): void
    {
        $data = $this->contexte();
        $contexte = ['id_seance' => $data['seance']->id, 'id_matiere' => $data['cours']->module->id_matiere,
            'id_promotion' => $data['promotion']->id, 'id_annee_academique' => $data['annee']->id];
        $url = '/api/v1/enseignant/notes/tableau?'.http_build_query($contexte);
        $this->getJson($url)->assertOk()->assertExactJson([]);
        $this->presences($data);
        $this->putJson('/api/v1/enseignant/notes', [...$contexte, 'notes' => [
            ['id_etudiant' => $data['etudiants'][0]->id, 'evaluation' => 'devoir_1', 'note' => 10],
            ['id_etudiant' => $data['etudiants'][0]->id, 'evaluation' => 'examen', 'note' => 18],
        ]])->assertOk();
        $notes = NoteCours::orderBy('id')->get();
        $this->getJson($url)->assertOk()->assertExactJson([
            ['id' => $notes[0]->id, 'id_etudiant' => $data['etudiants'][0]->id, 'evaluation' => 'devoir_1', 'note' => 10],
            ['id' => $notes[1]->id, 'id_etudiant' => $data['etudiants'][0]->id, 'evaluation' => 'examen', 'note' => 18],
        ]);
        $this->getJson('/api/v1/enseignant/notes/tableau')->assertUnprocessable();
        Sanctum::actingAs($data['autreEnseignant']);
        $this->getJson($url)->assertNotFound();
        Sanctum::actingAs($data['admin']);
        $this->getJson($url)->assertForbidden();
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
        $this->putJson($url, ['notes' => [['id_etudiant' => $data['etudiants'][0]->id, 'note' => null]]])->assertUnprocessable();
    }

    public function test_acces_limite_a_affectation_et_promotion(): void
    {
        $this->getJson('/api/v1/enseignant/notes')->assertUnauthorized();
        $data = $this->contexte();
        $this->getJson('/api/v1/enseignant/notes?id_annee_academique='.$data['annee']->id)->assertOk()
            ->assertJsonCount(2, 'enseignements');
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
        $feuille = FeuilleNotes::create(['id_annee_academique' => $data['annee']->id,
            'id_promotion' => $data['promotion']->id, 'id_cours' => $autreCours->id, 'updated_by' => $data['enseignant']->id]);
        $feuille->notes()->create(['id_etudiant' => $data['etudiants'][0]->id, 'note' => 10]);
        $ancienne = AnneeAcademique::whereKeyNot($data['annee']->id)->firstOrFail();
        $feuille = FeuilleNotes::create(['id_annee_academique' => $ancienne->id,
            'id_promotion' => $data['promotion']->id, 'id_cours' => $autreCours->id, 'updated_by' => $data['enseignant']->id]);
        $feuille->notes()->create(['id_etudiant' => $data['etudiants'][0]->id, 'note' => 20]);
        $this->putJson($this->url($data), ['notes' => [['id_etudiant' => $data['etudiants'][0]->id, 'note' => 18]]])
            ->assertOk()->assertJsonPath('feuille_notes.etudiants.0.moyenne_matiere', null);
        AffectationEnseignant::where('enseignant_id', $data['enseignant']->id)->update(['date_fin' => '2026-09-14']);
        $this->getJson($this->url($data))->assertNotFound();
    }

    public function test_seance_prevue_bloque_mais_autre_seance_sans_presence_ne_bloque_pas(): void
    {
        $data = $this->contexte();
        $data['seance']->update(['statut' => 'prevue']);
        $this->getJson($this->url($data))->assertOk()->assertJsonPath('feuille_notes.saisie_ouverte', false);
        $data['seance']->update(['statut' => 'realisee']);
        $this->presences($data);
        $autre = $data['seance']->replicate();
        $autre->date_prevue = '2026-09-21';
        $autre->save();
        $this->getJson($this->url($data))->assertOk()->assertJsonPath('feuille_notes.saisie_ouverte', true)
            ->assertJsonPath('feuille_notes.seances_realisees', 1);
    }

    public function test_transmission_matiere_accepte_les_identifiants_dans_url(): void
    {
        $data = $this->contexte();
        $this->presences($data);
        $url = '/api/v1/enseignant/notes/transmettre?'.http_build_query([
            'id_annee_academique' => $data['annee']->id,
            'id_promotion' => $data['promotion']->id,
            'id_seance' => $data['seance']->id,
            'id_matiere' => $data['cours']->module->id_matiere,
        ]);
        $this->postJson($url, ['notes' => [
            ['id_etudiant' => $data['etudiants'][0]->id, 'note' => 15.5],
        ]])->assertOk()->assertJsonPath('feuille_notes.statut', 'transmise');
        $this->postJson($url)->assertOk()->assertJsonCount(1, 'feuille_notes.historique');
    }

    public function test_notes_isolees_par_seance_et_seance_autre_enseignant_interdite(): void
    {
        $data = $this->contexte();
        $this->presences($data);
        $autre = $data['seance']->replicate();
        $autre->date_prevue = '2026-09-21';
        $autre->save();
        $contexte = ['id_seance' => $data['seance']->id, 'id_matiere' => $data['cours']->module->id_matiere,
            'id_promotion' => $data['promotion']->id, 'id_annee_academique' => $data['annee']->id];
        $payload = ['notes' => [['id_etudiant' => $data['etudiants'][0]->id, 'note' => 10]]];
        $this->putJson('/api/v1/enseignant/notes', [...$contexte, ...$payload])->assertOk()
            ->assertJsonPath('feuille_notes.id_seance', $data['seance']->id);
        $contexte['id_seance'] = $autre->id;
        $this->putJson('/api/v1/enseignant/notes', [...$contexte, ...$payload])->assertUnprocessable();
        $this->presences([...$data, 'seance' => $autre]);
        $payload['notes'][0]['note'] = 18;
        $this->putJson('/api/v1/enseignant/notes', [...$contexte, ...$payload])->assertOk()
            ->assertJsonPath('feuille_notes.id_seance', $autre->id);
        $this->assertDatabaseCount('feuilles_notes', 2);
        $this->getJson('/api/v1/enseignant/notes/tableau?'.http_build_query($contexte))->assertOk()
            ->assertJsonCount(1)->assertJsonPath('0.note', 18);
        $this->getJson('/api/v1/enseignant/notes/feuille?'.http_build_query($contexte))->assertOk()
            ->assertJsonPath('feuille_notes.etudiants.0.moyenne_matiere', null);
        $this->getJson('/api/v1/enseignant/transmissions-notes/tableau?id_seance='.$autre->id)->assertOk()
            ->assertJsonCount(1)->assertJsonPath('0.notes.0.note', 18)->assertJsonPath('0.id_seance', $autre->id);
        $this->getJson('/api/v1/enseignant/transmissions-notes/feuilles?id_seance='.$data['seance']->id)->assertOk()
            ->assertJsonCount(1, 'feuilles_notes')->assertJsonPath('feuilles_notes.0.notes.0.note', 10);
        foreach (['tableau', 'feuilles'] as $format) {
            $racine = $format === 'tableau' ? '0' : 'feuilles_notes.0';
            $this->getJson('/api/v1/enseignant/transmissions-notes/'.$format.'?'.http_build_query($contexte))
                ->assertOk()->assertJsonPath($racine.'.id_seance', $autre->id)
                ->assertJsonPath($racine.'.matiere.id', $contexte['id_matiere'])
                ->assertJsonPath($racine.'.notes.0.note', 18);
        }
        unset($contexte['id_seance']);
        $this->putJson('/api/v1/enseignant/notes', [...$contexte, ...$payload])->assertUnprocessable()
            ->assertJsonValidationErrors('id_seance');
        $contexte['id_seance'] = 999999;
        $this->getJson('/api/v1/enseignant/notes/tableau?'.http_build_query($contexte))->assertNotFound();
        $contexte['id_seance'] = $autre->id;
        $autre->update(['id_promotion' => $data['horsPromotion']->inscriptions()->firstOrFail()->id_promotion]);
        $this->putJson('/api/v1/enseignant/notes', [...$contexte, ...$payload])->assertNotFound();
        $autre->update(['id_promotion' => $data['promotion']->id]);
        $contexte['id_seance'] = $autre->id;
        $autre->update(['enseignant_id' => $data['autreEnseignant']->id]);
        $this->getJson('/api/v1/enseignant/notes/tableau?'.http_build_query($contexte))->assertNotFound();
        $this->putJson('/api/v1/enseignant/notes', [...$contexte, ...$payload])->assertNotFound();
    }

    public function test_correction_autorisee_appliquee_et_tracee_sans_deverrouillage(): void
    {
        $data = $this->contexte();
        $this->presences($data);
        $this->postJson(str_replace('?', '/transmettre?', $this->url($data)), [
            'notes' => [['id_etudiant' => $data['etudiants'][0]->id, 'note' => 12]],
        ])->assertOk();
        $note = NoteCours::firstOrFail();
        $base = '/api/v1/administration/corrections-notes';
        $payload = ['id_seance' => $data['seance']->id, 'id_note' => $note->id, 'note_proposee' => 16.5, 'motif' => 'Erreur de saisie'];
        $this->postJson($base, $payload)->assertForbidden();
        Sanctum::actingAs($data['admin']);
        $id = $this->postJson($base, $payload)->assertCreated()->json('correction.id');
        $this->postJson($base, $payload)->assertUnprocessable();
        $this->postJson("$base/$id/appliquer")->assertUnprocessable();
        $this->assertEquals(12, $note->fresh()->note);
        $this->postJson("$base/$id/autoriser")->assertOk()->assertJsonPath('correction.statut', 'autorisee');
        $this->postJson("$base/$id/appliquer")->assertOk()->assertJsonPath('correction.note_finale', 16.5);
        $this->postJson("$base/$id/appliquer")->assertUnprocessable();
        $this->getJson("$base/$id")->assertOk()->assertJsonCount(3, 'historique');
        $this->getJson($base.'?statut=appliquee')->assertOk()->assertJsonCount(1, 'data');
        Sanctum::actingAs($data['enseignant']);
        $this->getJson($this->url($data))->assertOk()
            ->assertJsonPath('feuille_notes.saisie_ouverte', false)
            ->assertJsonPath('feuille_notes.etudiants.0.moyenne_matiere', null);
    }

    public function test_correction_note_matiere_sans_cours(): void
    {
        $data = $this->contexte();
        $data['seance']->update(['id_cours' => null]);
        $data['seance']->creneau->update(['id_cours' => null]);
        $this->presences($data);
        $contexte = ['id_seance' => $data['seance']->id, 'id_matiere' => $data['cours']->module->id_matiere,
            'id_promotion' => $data['promotion']->id, 'id_annee_academique' => $data['annee']->id];
        $reponse = $this->putJson('/api/v1/enseignant/notes', [...$contexte,
            'notes' => [['id_etudiant' => $data['etudiants'][0]->id, 'note' => 12]],
        ])->assertOk();
        $idNote = $reponse->json('feuille_notes.etudiants.0.id_note');
        $base = '/api/v1/administration/corrections-notes';
        $payload = ['id_seance' => $data['seance']->id, 'id_note' => $idNote, 'note_proposee' => 16.5, 'motif' => 'Erreur de saisie par matière'];
        $this->postJson($base, $payload)->assertForbidden();
        Sanctum::actingAs($data['admin']);
        FeuilleNotes::firstOrFail()->update(['statut' => 'brouillon']);
        $this->postJson($base, $payload)->assertUnprocessable();
        Sanctum::actingAs($data['enseignant']);
        $this->postJson('/api/v1/enseignant/notes/transmettre', $contexte)->assertOk();
        Sanctum::actingAs($data['admin']);
        $id = $this->postJson($base, $payload)->assertCreated()->json('correction.id');
        $this->postJson($base, $payload)->assertUnprocessable();
        $this->postJson("$base/$id/appliquer")->assertUnprocessable();
        $this->postJson("$base/$id/autoriser")->assertOk();
        $this->postJson("$base/$id/appliquer")->assertOk()->assertJsonPath('correction.note_finale', 16.5);
        $this->getJson("$base/$id")->assertOk()->assertJsonCount(3, 'historique');
        $this->assertDatabaseHas('feuilles_notes', [...$contexte, 'id_cours' => null, 'statut' => 'transmise']);
        $this->assertDatabaseHas('notes_cours', ['id' => $idNote, 'note' => 16.5]);
        Sanctum::actingAs($data['enseignant']);
        $this->getJson('/api/v1/enseignant/notes/feuille?'.http_build_query($contexte))->assertOk()
            ->assertJsonPath('feuille_notes.cours', null)->assertJsonPath('feuille_notes.saisie_ouverte', false)
            ->assertJsonPath('feuille_notes.etudiants.0.notes.0.note', 16.5)
            ->assertJsonPath('feuille_notes.etudiants.0.moyenne_matiere', null);
    }

    public function test_validation_rejet_roles_et_detection_note_modifiee(): void
    {
        $data = $this->contexte();
        $this->presences($data);
        $this->putJson($this->url($data), ['notes' => [['id_etudiant' => $data['etudiants'][0]->id, 'note' => 12]]])->assertOk();
        $note = NoteCours::firstOrFail();
        $base = '/api/v1/administration/corrections-notes';
        $payload = ['id_seance' => $data['seance']->id, 'id_note' => $note->id, 'note_proposee' => 16, 'motif' => 'Erreur'];
        Sanctum::actingAs($data['admin']);
        FeuilleNotes::firstOrFail()->update(['statut' => 'brouillon']);
        $this->postJson($base, $payload)->assertUnprocessable();
        FeuilleNotes::firstOrFail()->update(['statut' => 'transmise']);
        foreach ([null, -1, 21, 12, 12.123] as $valeur) {
            $this->postJson($base, [...$payload, 'note_proposee' => $valeur])->assertUnprocessable();
        }
        $this->postJson($base, [...$payload, 'motif' => ' '])->assertUnprocessable();
        $this->postJson($base, [...$payload, 'id_note' => 99999])->assertNotFound();
        $id = $this->postJson($base, $payload)->assertCreated()->json('correction.id');
        $role = Role::create(['code' => 'ETUDIANT', 'libelle' => 'Etudiant']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));
        $this->postJson("$base/$id/autoriser")->assertForbidden();
        $this->postJson("$base/$id/rejeter", ['motif' => 'Non justifiée'])->assertForbidden();
        Sanctum::actingAs($data['admin']);
        $this->postJson("$base/$id/rejeter")->assertUnprocessable();
        $this->postJson("$base/$id/rejeter", ['motif' => 'Non justifiée'])->assertOk();
        $this->postJson("$base/$id/autoriser")->assertUnprocessable();
        $id = $this->postJson($base, [...$payload, 'note_proposee' => 0])->assertCreated()->json('correction.id');
        $this->postJson("$base/$id/autoriser")->assertOk();
        $note->update(['note' => 13]);
        $this->postJson("$base/$id/appliquer")->assertUnprocessable();
        $this->assertEquals(13, $note->fresh()->note);
        $this->assertDatabaseHas('corrections_notes', ['id' => $id, 'statut' => 'autorisee', 'note_finale' => null]);
    }
}
