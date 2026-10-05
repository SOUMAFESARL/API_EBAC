<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Bulletin;
use App\Models\Etudiant;
use App\Models\Inscription;
use App\Models\LigneBulletin;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReclamationsNotesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_reclamation_isolee_publication_doublon_et_traitement(): void
    {
        $base = '/api/v1/etudiant/reclamations-notes';
        $this->getJson($base)->assertUnauthorized();
        $role = Role::create(['code' => 'ETUDIANT', 'libelle' => 'Etudiant']);
        $user = User::factory()->create(['id_role' => $role->id]);
        $autreUser = User::factory()->create(['id_role' => $role->id]);
        $niveau = Niveau::create(['code' => 'N1', 'libelle' => 'Premiere annee', 'rang' => 1]);
        $promotion = Promotion::create(['num_promotion' => 4, 'annee_entree' => 2026, 'id_niveau' => $niveau->id]);
        $annee = AnneeAcademique::create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'active' => true]);
        $matiere = Matiere::create(['code' => 'MAT', 'libelle' => 'Theologie', 'id_niveau' => $niveau->id]);
        $lignes = [];
        foreach ([$user, $autreUser] as $index => $personne) {
            $etudiant = Etudiant::create(['user_id' => $personne->id, 'matricule' => 'ETU-'.$index, 'nom' => 'Koffi', 'prenoms' => 'Anne', 'date_inscription' => '2026-09-01']);
            $inscription = Inscription::create(['id_etudiant' => $etudiant->id, 'id_promotion' => $promotion->id, 'id_annee_academique' => $annee->id, 'date_inscription' => '2026-09-01']);
            $bulletin = Bulletin::create(['id_inscription' => $inscription->id, 'periode' => 'Annuel', 'statut' => 'Publié', 'date_publication' => now()]);
            $lignes[] = LigneBulletin::create(['id_bulletin' => $bulletin->id, 'id_matiere' => $matiere->id, 'note' => 12]);
        }
        Sanctum::actingAs($user);
        $payload = ['id_ligne_bulletin' => $lignes[0]->id, 'motif' => 'Ma copie indique 15 au lieu de 12'];
        $this->postJson($base, [...$payload, 'motif' => ' '])->assertUnprocessable();
        $this->postJson($base, [...$payload, 'id_ligne_bulletin' => $lignes[1]->id])->assertNotFound();
        $lignes[0]->bulletin->update(['statut' => 'Brouillon']);
        $this->postJson($base, $payload)->assertNotFound();
        $lignes[0]->bulletin->update(['statut' => 'Publié']);
        $lignes[0]->update(['note' => null]);
        $this->postJson($base, $payload)->assertUnprocessable();
        $lignes[0]->update(['note' => 12]);
        $id = $this->postJson($base, $payload)->assertCreated()->assertJsonPath('reclamation.note_contestee', 12)->json('reclamation.id');
        $this->postJson($base, $payload)->assertUnprocessable();
        $this->getJson($base)->assertOk()->assertJsonCount(1, 'data');
        Sanctum::actingAs($autreUser);
        $this->getJson($base)->assertOk()->assertJsonCount(0, 'data');
        $this->getJson($base.'/'.$id)->assertNotFound();
        $adminBase = '/api/v1/administration/reclamations-notes';
        $this->getJson($adminBase)->assertForbidden();
        $this->postJson($adminBase.'/'.$id.'/traiter', ['statut' => 'acceptee', 'reponse' => 'Verifie'])->assertForbidden();
        $roleAdmin = Role::create(['code' => 'SECRETARIAT', 'libelle' => 'Secretariat']);
        $admin = User::factory()->create(['id_role' => $roleAdmin->id]);
        Sanctum::actingAs($admin);
        $this->getJson($adminBase.'?statut=en_attente')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson($adminBase.'/'.$id)->assertOk();
        $this->postJson($adminBase.'/'.$id.'/traiter', ['statut' => 'acceptee', 'reponse' => ' '])->assertUnprocessable();
        $this->postJson($adminBase.'/'.$id.'/traiter', ['statut' => 'acceptee', 'reponse' => 'Erreur confirmee, correction a demander'])->assertOk()
            ->assertJsonPath('reclamation.traitee_par', $admin->id);
        $this->postJson($adminBase.'/'.$id.'/traiter', ['statut' => 'rejetee', 'reponse' => 'Refus'])->assertUnprocessable();
        $this->assertEquals(12, $lignes[0]->fresh()->note);
        Sanctum::actingAs($user);
        $this->getJson($base.'/'.$id)->assertOk()->assertJsonPath('reclamation.statut', 'acceptee');
        $this->getJson($base.'?per_page=101')->assertUnprocessable();
        $user->update(['is_active' => false]);
        $this->postJson($base, $payload)->assertForbidden();
    }
}
