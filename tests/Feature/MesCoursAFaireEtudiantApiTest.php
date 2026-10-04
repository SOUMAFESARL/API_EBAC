<?php

namespace Tests\Feature;

use App\Models\CoursAFaire;
use App\Models\Etudiant;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Role;
use App\Models\SeanceCahierTexte;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MesCoursAFaireEtudiantApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_affiche_uniquement_les_cours_a_faire_du_compte_connecte(): void
    {
        $role = Role::create(['code' => 'ETUDIANT', 'libelle' => 'Etudiant']);
        $user = User::factory()->create(['id_role' => $role->id]);
        $etudiant = Etudiant::create(['user_id' => $user->id, 'matricule' => 'ETU-1', 'nom' => 'Koffi', 'prenoms' => 'Anne', 'date_inscription' => '2026-09-01']);
        $autre = Etudiant::create(['matricule' => 'ETU-2', 'nom' => 'Autre', 'prenoms' => 'Etudiant', 'date_inscription' => '2026-09-01']);
        $niveau = Niveau::create(['code' => 'N1', 'libelle' => 'Premiere annee', 'rang' => 1]);
        $matiere = Matiere::create(['code' => 'MAT-1', 'libelle' => 'Theologie', 'id_niveau' => $niveau->id]);
        foreach ([[$etudiant, 'a_faire'], [$autre, 'a_faire'], [$etudiant, 'termine']] as $index => [$personne, $statut]) {
            $seance = SeanceCahierTexte::create(['enseignant_id' => $user->id, 'id_niveau' => $niveau->id, 'id_matiere' => $matiere->id,
                'date_prevue' => '2026-09-'.(14 + $index), 'heure_debut_prevue' => '08:00:00', 'statut' => 'realisee', 'theme_traite' => 'Les conciles']);
            CoursAFaire::create(['id_etudiant' => $personne->id, 'id_matiere' => $matiere->id, 'id_seance' => $seance->id, 'statut' => $statut, 'motif' => 'absence']);
        }
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/etudiant/cours-a-faire')->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('cours_a_faire.0.matiere.libelle', 'Theologie')
            ->assertJsonPath('cours_a_faire.0.seance.theme_traite', 'Les conciles')
            ->assertJsonPath('cours_a_faire.0.cours', null);
        $this->getJson('/api/v1/etudiant/cours-a-faire?page=2&per_page=1')->assertOk()->assertJsonCount(0, 'cours_a_faire');
        $this->getJson('/api/v1/etudiant/cours-a-faire?per_page=101')->assertUnprocessable();
        $seance = CoursAFaire::where('id_etudiant', $etudiant->id)->where('statut', 'a_faire')->firstOrFail()->seance;
        $seance->delete();
        $this->getJson('/api/v1/etudiant/cours-a-faire')->assertOk()->assertJsonPath('meta.total', 0);
        CoursAFaire::where('id_etudiant', $etudiant->id)->delete();
        $this->getJson('/api/v1/etudiant/cours-a-faire')->assertOk()->assertJsonCount(0, 'cours_a_faire')->assertJsonPath('meta.total', 0);
    }

    public function test_acces_protege_et_fiche_etudiant_obligatoire(): void
    {
        $this->getJson('/api/v1/etudiant/cours-a-faire')->assertUnauthorized();
        $role = Role::create(['code' => 'ENSEIGNANT', 'libelle' => 'Enseignant']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));
        $this->getJson('/api/v1/etudiant/cours-a-faire')->assertForbidden();
        $role = Role::create(['code' => 'ETUDIANT', 'libelle' => 'Etudiant']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));
        $this->getJson('/api/v1/etudiant/cours-a-faire')->assertNotFound();
    }
}
