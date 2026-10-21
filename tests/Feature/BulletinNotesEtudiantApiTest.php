<?php

namespace Tests\Feature;

use App\Models\{AnneeAcademique, Etudiant, FeuilleNotes, Inscription, Matiere, Niveau, Promotion, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BulletinNotesEtudiantApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulletin_calcule_sur_notes_validees_et_acces(): void
    {
        $users = [];
        foreach (['ADMIN', 'SECRETARIAT', 'SECRETAIRE_ACADEMIQUE', 'DIRECTION', 'ENSEIGNANT', 'ETUDIANT'] as $code) {
            $role = Role::create(['code' => $code, 'libelle' => $code]);
            $users[$code] = User::factory()->create(['id_role' => $role->id]);
        }
        $annee = AnneeAcademique::create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31']);
        $niveau = Niveau::create(['code' => 'A1', 'libelle' => 'Annee 1', 'rang' => 1]);
        $promotion = Promotion::create(['num_promotion' => 17, 'annee_entree' => 2026, 'id_niveau' => $niveau->id]);
        $etudiant = Etudiant::create(['matricule' => 'ETU-1', 'nom' => 'KONE', 'prenoms' => 'Test', 'date_inscription' => '2026-09-01']);
        Inscription::create(['id_etudiant' => $etudiant->id, 'id_promotion' => $promotion->id, 'id_annee_academique' => $annee->id, 'date_inscription' => '2026-09-01']);
        $feuilles = [];
        foreach ([['INF', 2, 12, 'validee_direction'], ['MATH', 4, 15, 'validee_direction'], ['NON', 10, 0, 'validee_secretariat']] as [$code, $coefficient, $note, $statut]) {
            $matiere = Matiere::create(['code' => $code, 'libelle' => $code, 'id_niveau' => $niveau->id, 'coefficient' => $coefficient, 'note_validation' => 13]);
            $feuille = FeuilleNotes::create(['id_matiere' => $matiere->id, 'id_promotion' => $promotion->id,
                'id_annee_academique' => $annee->id, 'statut' => $statut, 'updated_by' => $users['ENSEIGNANT']->id, 'transmise_par' => $users['ENSEIGNANT']->id]);
            $feuille->notes()->create(['id_etudiant' => $etudiant->id, 'note' => $note]);
            $feuilles[] = $feuille;
        }
        $url = '/api/v1/administration/bulletins-notes/'.$etudiant->id.'?id_promotion='.$promotion->id.'&id_annee_academique='.$annee->id;
        $this->getJson($url)->assertUnauthorized();
        foreach (['ADMIN', 'SECRETARIAT', 'SECRETAIRE_ACADEMIQUE', 'DIRECTION'] as $code) {
            Sanctum::actingAs($users[$code]);
            $this->getJson($url)->assertOk()->assertJsonPath('bulletin.etudiant.id', $etudiant->id)
                ->assertJsonPath('bulletin.niveau.id', $niveau->id)->assertJsonPath('bulletin.nombre_matieres', 2)
                ->assertJsonPath('bulletin.moyenne_generale', 14)->assertJsonPath('bulletin.total_coefficients', 6)
                ->assertJsonPath('bulletin.matieres_validees', 1)
                ->assertJsonPath('bulletin.credits_valides', 4)
                ->assertJsonPath('bulletin.regle_credits', 'somme_coefficients_matieres_validees')
                ->assertJsonPath('bulletin.matieres.0.decision', 'ajourne')
                ->assertJsonPath('bulletin.matieres.1.decision', 'valide')
                ->assertJsonPath('bulletin.matieres.0.enseignants.0.id', $users['ENSEIGNANT']->id)
                ->assertJsonPath('bulletin.matieres.0.annee', '2026-2027');
        }
        $feuilles[0]->update(['statut' => 'transmise']);
        $this->getJson($url)->assertOk()->assertJsonPath('bulletin.moyenne_generale', 15)->assertJsonPath('bulletin.nombre_matieres', 1);
        $feuilles[1]->update(['statut' => 'transmise']);
        $this->getJson($url)->assertOk()->assertJsonPath('bulletin.moyenne_generale', null)->assertJsonPath('bulletin.credits_valides', 0)->assertJsonCount(0, 'bulletin.matieres');
        $autre = Etudiant::create(['matricule' => 'ETU-2', 'nom' => 'Autre', 'prenoms' => 'Test', 'date_inscription' => '2026-09-01']);
        $this->getJson(str_replace('/'.$etudiant->id.'?', '/'.$autre->id.'?', $url))->assertNotFound();
        $this->getJson('/api/v1/administration/bulletins-notes/'.$etudiant->id)->assertUnprocessable();
        foreach (['ENSEIGNANT', 'ETUDIANT'] as $code) {
            Sanctum::actingAs($users[$code]);
            $this->getJson($url)->assertForbidden();
        }
        Sanctum::actingAs($users['SECRETARIAT']);
        $users['SECRETARIAT']->update(['is_active' => false]);
        $this->getJson($url)->assertForbidden();
        $this->assertDatabaseCount('bulletins', 0);
    }
}
