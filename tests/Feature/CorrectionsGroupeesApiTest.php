<?php

namespace Tests\Feature;

use App\Models\{AnneeAcademique, CorrectionNote, Etudiant, FeuilleNotes, Inscription, Matiere, Niveau, Promotion, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CorrectionsGroupeesApiTest extends TestCase
{
    use RefreshDatabase;

    private function contexte(): array
    {
        $users = [];
        foreach (['SECRETARIAT', 'SECRETAIRE_ACADEMIQUE', 'DIRECTION', 'ENSEIGNANT'] as $code) {
            $role = Role::create(['code' => $code, 'libelle' => $code]);
            $users[$code] = User::factory()->create(['id_role' => $role->id]);
        }
        $annee = AnneeAcademique::create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'active' => true]);
        $niveau = Niveau::create(['code' => 'N1', 'libelle' => 'Niveau 1', 'rang' => 1]);
        $promotion = Promotion::create(['num_promotion' => 17, 'annee_entree' => 2026, 'id_niveau' => $niveau->id]);
        $matiere = Matiere::create(['code' => 'MAT-1', 'libelle' => 'Theologie', 'id_niveau' => $niveau->id]);
        $feuille = FeuilleNotes::create(['id_annee_academique' => $annee->id, 'id_promotion' => $promotion->id,
            'id_matiere' => $matiere->id, 'statut' => 'validee_direction', 'updated_by' => $users['ENSEIGNANT']->id]);
        $corrections = collect();
        foreach ([['Participation', 12, 16], ['Participation', 14, 18], ['Expose', 10, 15]] as $index => [$evaluation, $initiale, $proposee]) {
            $etudiant = Etudiant::create(['matricule' => 'ETU-'.$index, 'nom' => 'KONE', 'prenoms' => 'Test', 'date_inscription' => '2026-09-01']);
            Inscription::create(['id_etudiant' => $etudiant->id, 'id_promotion' => $promotion->id, 'id_annee_academique' => $annee->id, 'date_inscription' => '2026-09-01']);
            $note = $feuille->notes()->create(['id_etudiant' => $etudiant->id, 'note' => $initiale, 'evaluation' => $evaluation]);
            $corrections->push(CorrectionNote::create(['id_note' => $note->id, 'note_initiale' => $initiale,
                'note_proposee' => $proposee, 'motif' => 'Erreur de report', 'statut' => 'en_attente', 'demande_par' => $users['ENSEIGNANT']->id]));
        }

        return [$users, $feuille, $corrections];
    }

    public function test_liste_et_detail_regroupent_sans_melanger_les_evaluations(): void
    {
        [$users, $feuille, $corrections] = $this->contexte();
        Sanctum::actingAs($users['SECRETARIAT']);
        $base = '/api/v1/administration/corrections-notes/groupes';
        $this->getJson($base)->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson($base.'?search=Participation&per_page=1')->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('feuilles_corrections.0.nombre_corrections', 2);
        $this->getJson($base.'?search=17')->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson($base.'?search=inconnu')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson($base.'/'.$feuille->id.'?evaluation=Participation')->assertOk()
            ->assertJsonPath('feuille_corrections.nombre_corrections', 2)
            ->assertJsonPath('feuille_corrections.notes_saisies', 2)
            ->assertJsonPath('feuille_corrections.effectif', 3)
            ->assertJsonPath('feuille_corrections.moyenne_cours', 13)
            ->assertJsonPath('feuille_corrections.actions.peut_appliquer', true)
            ->assertJsonCount(2, 'feuille_corrections.notes')
            ->assertJsonPath('feuille_corrections.notes.0.corrections.0.note_initiale', 12)
            ->assertJsonPath('feuille_corrections.notes.0.corrections.0.note_proposee', 16);
        $this->getJson($base.'/'.$feuille->id)->assertUnprocessable();
        $this->getJson($base.'/'.$feuille->id.'?evaluation=absente')->assertNotFound();
    }

    public function test_application_groupee_atomique_et_controle_des_identifiants(): void
    {
        [$users, $feuille, $corrections] = $this->contexte();
        Sanctum::actingAs($users['SECRETARIAT']);
        $url = '/api/v1/administration/corrections-notes/groupes/'.$feuille->id.'/appliquer';
        $payload = ['evaluation' => 'Participation', 'ids_corrections' => $corrections->take(2)->pluck('id')->all()];
        $this->postJson($url, [...$payload, 'ids_corrections' => $corrections->pluck('id')->all()])->assertUnprocessable();
        $corrections[1]->note->update(['note' => 11]);
        $this->postJson($url, $payload)->assertUnprocessable();
        $this->assertSame('en_attente', $corrections[0]->fresh()->statut);
        $this->assertEquals(12, $corrections[0]->note->fresh()->note);
        $this->assertDatabaseCount('traces_corrections_notes', 0);
        $corrections[1]->note->update(['note' => 14]);
        $this->postJson($url, $payload)->assertOk()->assertJsonPath('feuille_corrections.statut', 'appliquee')
            ->assertJsonPath('feuille_corrections.moyenne_cours', 17)
            ->assertJsonPath('feuille_corrections.actions.peut_appliquer', false);
        $this->assertEquals(16, $corrections[0]->note->fresh()->note);
        $this->assertEquals(18, $corrections[1]->note->fresh()->note);
        $this->assertSame('en_attente', $corrections[2]->fresh()->statut);
        $this->assertDatabaseCount('traces_corrections_notes', 4);
        $this->postJson($url, $payload)->assertUnprocessable();
        $this->assertDatabaseCount('traces_corrections_notes', 4);
        $this->assertSame('transmise', $feuille->fresh()->statut);
        $this->getJson('/api/v1/administration/corrections-notes/groupes?statut=appliquee')
            ->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_pagination_et_notes_sans_demande(): void
    {
        [$users, $feuille, $corrections] = $this->contexte();
        $feuille->notes()->create(['id_etudiant' => $corrections[2]->note->id_etudiant, 'evaluation' => 'Participation', 'note' => 0]);
        Sanctum::actingAs($users['SECRETARIAT']);
        $base = '/api/v1/administration/corrections-notes/groupes';
        $this->getJson($base.'?per_page=1&page=2')->assertOk()
            ->assertJsonPath('meta.total', 2)->assertJsonCount(1, 'feuilles_corrections');
        $this->getJson($base.'/'.$feuille->id.'?evaluation=Expose')->assertOk()
            ->assertJsonPath('feuille_corrections.evaluation', 'Expose')
            ->assertJsonCount(1, 'feuille_corrections.notes');
        $this->getJson($base.'/'.$feuille->id.'?evaluation=Participation')->assertOk()
            ->assertJsonCount(3, 'feuille_corrections.notes')
            ->assertJsonPath('feuille_corrections.notes_saisies', 3)
            ->assertJsonCount(0, 'feuille_corrections.notes.2.corrections');
        $this->postJson($base.'/'.$feuille->id.'/appliquer', ['evaluation' => 'Expose', 'ids_corrections' => [$corrections[2]->id]])
            ->assertOk()->assertJsonPath('feuille_corrections.statut', 'appliquee');
    }

    public function test_rejet_groupe_et_acces_proteges(): void
    {
        [$users, $feuille, $corrections] = $this->contexte();
        $base = '/api/v1/administration/corrections-notes/groupes';
        $payload = ['evaluation' => 'Participation', 'ids_corrections' => $corrections->take(2)->pluck('id')->all()];
        $this->getJson($base)->assertUnauthorized();
        $this->postJson($base.'/'.$feuille->id.'/appliquer', $payload)->assertUnauthorized();
        Sanctum::actingAs($users['ENSEIGNANT']);
        $this->getJson($base)->assertForbidden();
        Sanctum::actingAs($users['DIRECTION']);
        $this->postJson($base.'/'.$feuille->id.'/appliquer', $payload)->assertForbidden();
        $this->postJson($base.'/'.$feuille->id.'/rejeter', [...$payload, 'motif' => 'Refus'])->assertForbidden();
        Sanctum::actingAs($users['SECRETAIRE_ACADEMIQUE']);
        $url = $base.'/'.$feuille->id.'/rejeter';
        $this->postJson($url, $payload)->assertUnprocessable();
        $this->postJson($url, [...$payload, 'motif' => 'Copie non justifiee'])->assertOk()
            ->assertJsonPath('feuille_corrections.statut', 'rejetee')
            ->assertJsonPath('feuille_corrections.actions.peut_appliquer', false);
        $this->assertEquals(12, $corrections[0]->note->fresh()->note);
        $this->assertDatabaseHas('traces_corrections_notes', ['id_correction' => $corrections[0]->id, 'action' => 'rejetee', 'details' => 'Copie non justifiee']);
        $this->postJson($url, [...$payload, 'motif' => 'Encore'])->assertUnprocessable();
        $users['SECRETAIRE_ACADEMIQUE']->update(['is_active' => false]);
        $this->getJson($base)->assertForbidden();
    }
}
