<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Etudiant;
use App\Models\FeuilleNotes;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotesTransmisesApiTest extends TestCase
{
    use RefreshDatabase;

    private function contexte(): array
    {
        $users = [];
        foreach (['ADMIN', 'SECRETAIRE_ACADEMIQUE', 'SECRETARIAT', 'DIRECTION', 'ENSEIGNANT', 'ETUDIANT'] as $code) {
            $role = Role::create(['code' => $code, 'libelle' => $code]);
            $users[$code] = User::factory()->create(['id_role' => $role->id]);
        }
        $annee = AnneeAcademique::create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'active' => true]);
        $niveau = Niveau::create(['code' => 'N1', 'libelle' => 'Niveau 1', 'rang' => 1]);
        $promotion = Promotion::create(['num_promotion' => 1, 'annee_entree' => 2026, 'id_niveau' => $niveau->id]);
        $matiere = Matiere::create(['code' => 'MAT-1', 'libelle' => 'Theologie', 'id_niveau' => $niveau->id]);
        $feuille = FeuilleNotes::create(['id_annee_academique' => $annee->id, 'id_promotion' => $promotion->id,
            'id_matiere' => $matiere->id, 'statut' => 'transmise', 'date_transmission' => now(), 'updated_by' => $users['ENSEIGNANT']->id]);
        $etudiant = Etudiant::create(['matricule' => 'ETU-1', 'nom' => 'KONE', 'prenoms' => 'Test', 'date_inscription' => '2026-09-01']);
        $note = $feuille->notes()->create(['id_etudiant' => $etudiant->id, 'note' => 14]);

        return [$users, $feuille, $note];
    }

    private function url(FeuilleNotes $feuille): string
    {
        return '/api/v1/administration/notes-transmises/'.$feuille->id;
    }

    public function test_statut_affiche_et_filtres_selon_le_destinataire(): void
    {
        [$users, $feuille] = $this->contexte();
        foreach (['SECRETARIAT', 'SECRETAIRE_ACADEMIQUE'] as $role) {
            Sanctum::actingAs($users[$role]);
            $this->getJson($this->url($feuille))->assertOk()
                ->assertJsonPath('feuille_notes.statut', 'a_verifier')
                ->assertJsonPath('feuille_notes.statut_workflow', 'transmise');
            $this->getJson('/api/v1/administration/notes-transmises?statut=a_verifier')
                ->assertOk()->assertJsonPath('meta.total', 1)
                ->assertJsonPath('feuilles_notes.0.statut', 'a_verifier');
        }
        $this->postJson($this->url($feuille).'/valider-secretariat')->assertOk();
        $this->postJson($this->url($feuille).'/transmettre-direction')->assertOk();
        Sanctum::actingAs($users['DIRECTION']);
        foreach (['transmise_direction' => 'en_attente', 'validee_direction' => 'validee', 'rejetee_direction' => 'rejetee'] as $interne => $affiche) {
            $feuille->update(['statut' => $interne]);
            $this->getJson($this->url($feuille))->assertOk()
                ->assertJsonPath('feuille_notes.statut', $affiche)
                ->assertJsonPath('feuille_notes.statut_workflow', $interne);
            $this->getJson('/api/v1/administration/notes-transmises?statut='.$affiche)
                ->assertOk()->assertJsonPath('meta.total', 1)
                ->assertJsonPath('feuilles_notes.0.statut', $affiche);
        }
        Sanctum::actingAs($users['SECRETAIRE_ACADEMIQUE']);
        $this->getJson($this->url($feuille))->assertOk()
            ->assertJsonPath('feuille_notes.statut', 'rejetee_direction');
        $this->assertSame('rejetee_direction', $feuille->fresh()->statut);
    }

    public function test_liste_transmise_contient_le_tableau_des_notes_et_son_enseignant(): void
    {
        [$users, $feuille, $note] = $this->contexte();
        $feuille->update(['transmise_par' => $users['ENSEIGNANT']->id]);
        $brouillon = $feuille->replicate();
        $brouillon->statut = 'brouillon';
        $brouillon->id_matiere = null;
        $brouillon->save();

        Sanctum::actingAs($users['SECRETAIRE_ACADEMIQUE']);
        $this->postJson($this->url($feuille).'/valider-secretariat')->assertOk();
        $this->getJson('/api/v1/administration/notes-transmises')->assertOk()
            ->assertJsonCount(1, 'feuilles_notes')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('feuilles_notes.0.enseignant.id', $users['ENSEIGNANT']->id)
            ->assertJsonPath('feuilles_notes.0.nombre_notes', 1)
            ->assertJsonCount(1, 'feuilles_notes.0.notes')
            ->assertJsonPath('feuilles_notes.0.notes.0.id', $note->id)
            ->assertJsonPath('feuilles_notes.0.notes.0.note', 14)
            ->assertJsonPath('feuilles_notes.0.notes.0.etudiant.matricule', 'ETU-1');
        $this->getJson('/api/v1/administration/notes-transmises?statut=transmise')->assertOk()
            ->assertJsonCount(0, 'feuilles_notes');
    }

    public function test_validation_secretariat_transmission_et_validation_direction(): void
    {
        [$users, $feuille] = $this->contexte();
        $url = $this->url($feuille);
        Sanctum::actingAs($users['SECRETAIRE_ACADEMIQUE']);
        $this->postJson($url.'/transmettre-direction')->assertOk()->assertJsonPath('feuille_notes.statut', 'transmise_direction');
        Sanctum::actingAs($users['DIRECTION']);
        $this->postJson($url.'/valider-direction')->assertOk()->assertJsonPath('feuille_notes.statut', 'validee');
        $this->postJson($url.'/rejeter-direction', ['motif' => 'Trop tard'])->assertUnprocessable();
        $this->getJson($url)->assertOk()->assertJsonCount(2, 'feuille_notes.historique')
            ->assertJsonPath('feuille_notes.historique.0.id_acteur', $users['SECRETAIRE_ACADEMIQUE']->id)
            ->assertJsonPath('feuille_notes.historique.1.id_acteur', $users['DIRECTION']->id)
            ->assertJsonPath('feuille_notes.notes.0.note', 14);
        $this->getJson('/api/v1/administration/notes-transmises?statut=validee_direction')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/administration/notes-transmises?statut=transmise')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/administration/notes-transmises?statut=invalide')->assertUnprocessable();
    }

    public function test_rejets_exigent_motif_et_direction_renvoie_au_secretariat(): void
    {
        [$users, $feuille] = $this->contexte();
        $url = $this->url($feuille);
        Sanctum::actingAs($users['SECRETARIAT']);
        $this->postJson($url.'/rejeter-secretariat')->assertUnprocessable()->assertJsonValidationErrors('motif');
        $this->postJson($url.'/transmettre-direction')->assertOk();
        Sanctum::actingAs($users['DIRECTION']);
        foreach ([[], ['motif' => '   '], ['motif' => str_repeat('a', 5001)]] as $payload) {
            $this->postJson($url.'/rejeter-direction', $payload)->assertUnprocessable()->assertJsonValidationErrors('motif');
        }
        $this->postJson($url.'/rejeter-direction', ['motif' => 'Verifier les notes'])->assertOk()
            ->assertJsonPath('feuille_notes.statut', 'rejetee')
            ->assertJsonPath('feuille_notes.historique.1.motif', 'Verifier les notes');
        Sanctum::actingAs($users['SECRETAIRE_ACADEMIQUE']);
        $this->postJson($url.'/transmettre-direction')->assertOk();
        Sanctum::actingAs($users['DIRECTION']);
        $this->postJson($url.'/valider-direction')->assertOk()->assertJsonCount(4, 'feuille_notes.historique');
    }

    public function test_motif_rejet_visible_dans_liste_et_detail_sans_motif_perime(): void
    {
        [$users, $feuille] = $this->contexte();
        Sanctum::actingAs($users['ADMIN']);
        $url = $this->url($feuille);
        foreach (['secretariat', 'direction'] as $origine) {
            if ($origine === 'direction') {
                $feuille->update(['statut' => 'transmise_direction']);
            }
            $motif = 'Erreur '.$origine;
            $this->postJson($url.'/rejeter-'.$origine, ['motif' => $motif])->assertOk()
                ->assertJsonPath('feuille_notes.motif', $motif);
            $this->getJson($url)->assertOk()->assertJsonPath('feuille_notes.motif', $motif);
            $this->getJson('/api/v1/administration/notes-transmises')->assertOk()
                ->assertJsonPath('feuilles_notes.0.motif', $motif);
        }
        $this->postJson($url.'/transmettre-direction')->assertOk()->assertJsonPath('feuille_notes.motif', null);
        $this->getJson('/api/v1/administration/notes-transmises')->assertOk()
            ->assertJsonPath('feuilles_notes.0.motif', null);
    }

    public function test_refus_secretariat_est_visible_et_ne_supprime_pas_notes(): void
    {
        [$users, $feuille] = $this->contexte();
        Sanctum::actingAs($users['SECRETAIRE_ACADEMIQUE']);
        $this->postJson($this->url($feuille).'/rejeter-secretariat', ['motif' => 'Notes incompletes'])->assertOk()
            ->assertJsonPath('feuille_notes.statut', 'rejetee_secretariat');
        $this->getJson($this->url($feuille))->assertOk()->assertJsonPath('feuille_notes.notes.0.note', 14)
            ->assertJsonPath('feuille_notes.historique.0.motif', 'Notes incompletes');
        $this->postJson($this->url($feuille).'/valider-secretariat')->assertUnprocessable();
    }

    public function test_direction_ne_transmet_pas_les_feuilles_du_secretariat(): void
    {
        [$users, $feuille] = $this->contexte();
        Sanctum::actingAs($users['DIRECTION']);
        $this->postJson($this->url($feuille).'/transmettre-direction')->assertForbidden();
        $feuille->update(['statut' => 'validee_secretariat']);
        $this->postJson($this->url($feuille).'/transmettre-direction')->assertForbidden();
    }

    public function test_roles_et_interdiction_de_sauter_des_etapes(): void
    {
        [$users, $feuille] = $this->contexte();
        $url = $this->url($feuille);
        $this->postJson($url.'/valider-secretariat')->assertUnauthorized();
        foreach (['ENSEIGNANT', 'ETUDIANT'] as $code) {
            Sanctum::actingAs($users[$code]);
            foreach (['valider-secretariat', 'rejeter-secretariat', 'transmettre-direction', 'valider-direction', 'rejeter-direction'] as $action) {
                $this->postJson($url.'/'.$action, ['motif' => 'Test'])->assertForbidden();
            }
        }
        Sanctum::actingAs($users['DIRECTION']);
        $this->postJson($url.'/valider-secretariat')->assertForbidden();
        $this->postJson($url.'/transmettre-direction')->assertForbidden();
        $this->postJson($url.'/rejeter-secretariat', ['motif' => 'Test'])->assertForbidden();
        $this->postJson($url.'/valider-direction')->assertUnprocessable();
        Sanctum::actingAs($users['SECRETAIRE_ACADEMIQUE']);
        $this->postJson($url.'/valider-direction')->assertForbidden();
        $this->postJson($url.'/rejeter-direction', ['motif' => 'Test'])->assertForbidden();
        $this->postJson('/api/v1/administration/notes-transmises/999999/valider-secretariat')->assertNotFound();
        $feuille->update(['statut' => 'brouillon']);
        $this->postJson($url.'/valider-secretariat')->assertUnprocessable();
        $this->assertDatabaseCount('historique_feuilles_notes', 0);
    }

    public function test_correction_bloque_validation_et_relance_controle_apres_application(): void
    {
        [$users, $feuille, $note] = $this->contexte();
        $seance = \App\Models\SeanceCahierTexte::create([
            'enseignant_id' => $users['ENSEIGNANT']->id,
            'id_niveau' => $feuille->promotion->id_niveau, 'id_matiere' => $feuille->id_matiere,
            'date_prevue' => '2026-09-14', 'heure_debut_prevue' => '08:00:00', 'statut' => 'realisee',
        ]);
        $feuille->update(['id_seance' => $seance->id]);
        Sanctum::actingAs($users['ADMIN']);
        $url = $this->url($feuille);
        $this->postJson($url.'/valider-secretariat')->assertOk();
        $this->postJson($url.'/transmettre-direction')->assertOk();
        $this->postJson($url.'/valider-direction')->assertOk();
        $response = $this->postJson('/api/v1/administration/corrections-notes', [
            'id_seance' => $seance->id, 'id_note' => $note->id, 'note_proposee' => 16, 'motif' => 'Erreur de saisie',
        ])->assertCreated();
        $correctionUrl = '/api/v1/administration/corrections-notes/'.$response->json('correction.id');
        $this->postJson($correctionUrl.'/autoriser')->assertOk();
        $this->postJson($correctionUrl.'/appliquer')->assertOk();
        $this->assertDatabaseHas('feuilles_notes', ['id' => $feuille->id, 'statut' => 'transmise']);
        $this->assertDatabaseHas('notes_cours', ['id' => $note->id, 'note' => 16]);
        $response = $this->postJson('/api/v1/administration/corrections-notes', [
            'id_seance' => $seance->id, 'id_note' => $note->id, 'note_proposee' => 17, 'motif' => 'Deuxieme verification',
        ])->assertCreated();
        $this->postJson($url.'/valider-secretariat')->assertUnprocessable()->assertJsonValidationErrors('notes');
        $this->postJson('/api/v1/administration/corrections-notes/'.$response->json('correction.id').'/rejeter', ['motif' => 'Non justifie'])->assertOk();
        $this->postJson($url.'/valider-secretariat')->assertOk();
    }
}
