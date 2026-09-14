<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Bulletin;
use App\Models\Cours;
use App\Models\Etudiant;
use App\Models\Inscription;
use App\Models\LigneBulletin;
use App\Models\Matiere;
use App\Models\Module;
use App\Models\Niveau;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MesMatieresEtudiantApiTest extends TestCase
{
    use RefreshDatabase;

    private function contexte(int $rang = 2): array
    {
        $role = Role::create(['code' => 'ETUDIANT', 'libelle' => 'Étudiant']);
        $user = User::factory()->create(['id_role' => $role->id]);
        $etudiant = Etudiant::create(['user_id' => $user->id, 'matricule' => 'ETU-001', 'nom' => 'KOFFI', 'prenoms' => 'Anne', 'date_inscription' => '2026-09-01']);
        $annee = AnneeAcademique::create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'active' => true]);
        $niveaux = collect([1, 2, 3])->map(fn ($numero) => Niveau::create(['code' => 'N'.$numero, 'libelle' => $numero.'e Année', 'rang' => $numero]));
        $promotion = Promotion::create(['num_promotion' => 17, 'annee_entree' => 2025, 'id_niveau' => $niveaux[$rang - 1]->id]);
        $inscription = Inscription::create(['id_etudiant' => $etudiant->id, 'id_promotion' => $promotion->id, 'id_annee_academique' => $annee->id, 'date_inscription' => '2026-09-01']);
        $matieres = $niveaux->map(fn ($niveau, $index) => Matiere::create(['code' => 'MAT-'.$index, 'libelle' => 'Matière niveau '.($index + 1), 'id_niveau' => $niveau->id, 'coefficient' => 2, 'note_validation' => 10]));
        $module = Module::create(['id_matiere' => $matieres[0]->id, 'libelle' => 'Pentateuque', 'ordre' => 1]);
        Cours::create(['id_module' => $module->id, 'code' => 'C-1', 'libelle' => 'Genèse et création', 'ordre' => 1]);

        return compact('user', 'etudiant', 'annee', 'inscription', 'matieres');
    }

    public function test_deuxieme_annee_voit_les_matieres_de_premiere_et_deuxieme_annee(): void
    {
        $data = $this->contexte(2);
        Sanctum::actingAs($data['user']);
        $this->getJson('/api/v1/etudiant/mes-matieres')->assertOk()
            ->assertJsonPath('niveau_actuel.rang', 2)
            ->assertJsonPath('promotion.num_promotion', 17)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonMissing(['id' => $data['matieres'][2]->id]);
    }

    public function test_premiere_annee_ne_voit_que_son_niveau(): void
    {
        $data = $this->contexte(1);
        Sanctum::actingAs($data['user']);
        $this->getJson('/api/v1/etudiant/mes-matieres')->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('matieres.0.niveau.rang', 1);
    }

    public function test_affiche_seulement_la_derniere_note_publiee_et_le_detail_des_cours(): void
    {
        $data = $this->contexte(2);
        $brouillon = Bulletin::create(['id_inscription' => $data['inscription']->id, 'periode' => 'Brouillon', 'statut' => 'Brouillon']);
        LigneBulletin::create(['id_bulletin' => $brouillon->id, 'id_matiere' => $data['matieres'][0]->id, 'note' => 19, 'coefficient' => 2]);
        $publie = Bulletin::create(['id_inscription' => $data['inscription']->id, 'periode' => 'Semestre 1', 'statut' => 'Publié', 'date_publication' => '2026-12-20 10:00:00']);
        LigneBulletin::create(['id_bulletin' => $publie->id, 'id_matiere' => $data['matieres'][0]->id, 'note' => 13, 'coefficient' => 2]);
        Sanctum::actingAs($data['user']);

        $this->getJson('/api/v1/etudiant/mes-matieres')->assertOk()
            ->assertJsonPath('matieres.0.note', 13)->assertJsonPath('matieres.0.statut', 'validee');
        $this->getJson('/api/v1/etudiant/mes-matieres/'.$data['matieres'][0]->id)->assertOk()
            ->assertJsonPath('matiere.nombre_cours', 1)
            ->assertJsonPath('matiere.modules.0.cours.0.libelle', 'Genèse et création')
            ->assertJsonPath('matiere.modules.0.cours.0.note', null);
        $this->getJson('/api/v1/etudiant/mes-matieres/'.$data['matieres'][2]->id)->assertNotFound();
    }

    public function test_acces_reserve_aux_etudiants_ayant_une_inscription(): void
    {
        $this->getJson('/api/v1/etudiant/mes-matieres')->assertUnauthorized();
        $role = Role::create(['code' => 'ENSEIGNANT', 'libelle' => 'Enseignant']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));
        $this->getJson('/api/v1/etudiant/mes-matieres')->assertForbidden();
    }
}
