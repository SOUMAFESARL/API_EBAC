<?php

namespace Tests\Feature;

use App\Models\{AnneeAcademique, Cours, Etudiant, FeuilleNotes, Inscription, Matiere, Module, Niveau, Promotion, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TableauNotesMatieresApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_tableau_alimente_apres_validation_et_isole_par_annee_promotion(): void
    {
        $role = Role::create(['code' => 'SECRETARIAT', 'libelle' => 'Secretariat']);
        $user = User::factory()->create(['id_role' => $role->id]);
        $annee = AnneeAcademique::create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31']);
        $niveau = Niveau::create(['code' => 'A1', 'libelle' => 'Annee 1', 'rang' => 1]);
        $promotion = Promotion::create(['num_promotion' => 17, 'annee_entree' => 2026, 'id_niveau' => $niveau->id]);
        $matiere = Matiere::create(['code' => 'INF', 'libelle' => 'Informatique', 'id_niveau' => $niveau->id, 'note_validation' => 12]);
        $etudiants = collect();
        foreach (['Alpha', 'Beta', 'Gamma'] as $index => $nom) {
            $etudiant = Etudiant::create(['matricule' => 'MAT-'.$index, 'nom' => $nom, 'prenoms' => 'Test', 'date_inscription' => '2026-09-01']);
            Inscription::create(['id_etudiant' => $etudiant->id, 'id_promotion' => $promotion->id, 'id_annee_academique' => $annee->id, 'date_inscription' => '2026-09-01']);
            $etudiants->push($etudiant);
        }
        $feuille = FeuilleNotes::create(['id_matiere' => $matiere->id, 'id_promotion' => $promotion->id,
            'id_annee_academique' => $annee->id, 'statut' => 'transmise', 'updated_by' => $user->id]);
        $feuille->notes()->create(['id_etudiant' => $etudiants[0]->id, 'note' => 14]);
        $feuille->notes()->create(['id_etudiant' => $etudiants[1]->id, 'note' => 9]);
        $url = '/api/v1/administration/notes-matieres?id_promotion='.$promotion->id.'&id_annee_academique='.$annee->id;
        $this->getJson($url)->assertUnauthorized();
        Sanctum::actingAs($user);
        $this->getJson($url)->assertOk()->assertJsonCount(0, 'matieres');
        $feuille->update(['statut' => 'validee_secretariat']);
        $this->getJson($url)->assertOk()->assertJsonCount(0, 'matieres');
        $feuille->update(['statut' => 'transmise_direction']);
        $directionRole = Role::create(['code' => 'DIRECTION', 'libelle' => 'Direction']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $directionRole->id]));
        $this->postJson('/api/v1/administration/notes-transmises/'.$feuille->id.'/valider-direction')->assertOk();
        $feuille->refresh();
        $this->getJson($url)->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('matieres.0.etudiants.0.note', 14)
            ->assertJsonPath('matieres.0.etudiants.0.decision', 'valide')
            ->assertJsonPath('matieres.0.etudiants.1.decision', 'ajourne')
            ->assertJsonPath('matieres.0.etudiants.2.note', null)
            ->assertJsonPath('matieres.0.etudiants.2.decision', 'en_attente');
        // A second validated evaluation contributes; a draft does not.
        $autre = $feuille->replicate();
        $autre->save();
        $autre->notes()->create(['id_etudiant' => $etudiants[0]->id, 'note' => 10]);
        $this->getJson($url)->assertOk()->assertJsonPath('matieres.0.etudiants.0.note', 12)
            ->assertJsonPath('matieres.0.etudiants.0.decision', 'valide');
        $autre->update(['statut' => 'transmise']);
        $this->getJson($url)->assertOk()->assertJsonPath('matieres.0.etudiants.0.note', 14);
        $this->getJson('/api/v1/administration/notes-matieres')->assertUnprocessable();
        $this->getJson($url.'&per_page=101')->assertUnprocessable();
        $autrePromotion = Promotion::create(['num_promotion' => 18, 'annee_entree' => 2026, 'id_niveau' => $niveau->id]);
        $autreAnnee = AnneeAcademique::create(['libelle' => '2025-2026', 'date_debut' => '2025-09-01', 'date_fin' => '2026-07-31']);
        foreach ([['id_promotion' => $autrePromotion->id], ['id_annee_academique' => $autreAnnee->id]] as $changement) {
            $isolee = $feuille->replicate()->fill($changement);
            $isolee->save();
            $isolee->notes()->create(['id_etudiant' => $etudiants[0]->id, 'note' => 0]);
        }
        $this->getJson($url)->assertOk()->assertJsonPath('matieres.0.etudiants.0.note', 14);
        // Courses resolve their matter through the module and use their coefficients.
        $module = Module::create(['code' => 'M1', 'libelle' => 'Module', 'id_matiere' => $matiere->id]);
        $cours = Cours::create(['code' => 'C1', 'libelle' => 'Cours', 'id_module' => $module->id, 'coefficient' => 3]);
        $coursFeuille = $feuille->replicate()->fill(['id_matiere' => null, 'id_cours' => $cours->id]);
        $coursFeuille->save();
        $coursFeuille->notes()->create(['id_etudiant' => $etudiants[0]->id, 'note' => 10]);
        $this->getJson($url)->assertOk()->assertJsonPath('matieres.0.etudiants.0.note', 11)
            ->assertJsonPath('matieres.0.etudiants.0.decision', 'ajourne');
        $enseignantRole = Role::create(['code' => 'ENSEIGNANT', 'libelle' => 'Enseignant']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $enseignantRole->id]));
        $this->getJson($url)->assertForbidden();
        Sanctum::actingAs($user);
        $user->update(['is_active' => false]);
        $this->getJson($url)->assertForbidden();
    }
}
