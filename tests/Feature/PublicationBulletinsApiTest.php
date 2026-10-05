<?php

namespace Tests\Feature;

use App\Models\{AnneeAcademique, Bulletin, Etudiant, Inscription, Niveau, Promotion, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PublicationBulletinsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_publication_par_roles_autorises_et_visibilite_etudiant(): void
    {
        $studentRole = Role::create(['code' => 'ETUDIANT', 'libelle' => 'Etudiant']);
        $student = User::factory()->create(['id_role' => $studentRole->id]);
        $etudiant = Etudiant::create(['user_id' => $student->id, 'matricule' => 'PUB-1', 'nom' => 'Koffi', 'prenoms' => 'Anne', 'date_inscription' => '2026-09-01']);
        $niveau = Niveau::create(['code' => 'N1', 'libelle' => 'Premiere annee', 'rang' => 1]);
        $promotion = Promotion::create(['num_promotion' => 4, 'annee_entree' => 2026, 'id_niveau' => $niveau->id]);
        $annee = AnneeAcademique::create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'active' => true]);
        $inscription = Inscription::create(['id_etudiant' => $etudiant->id, 'id_promotion' => $promotion->id, 'id_annee_academique' => $annee->id, 'date_inscription' => '2026-09-01']);

        foreach (['ADMIN', 'DIRECTION', 'SECRETARIAT', 'SECRETAIRE_ACADEMIQUE'] as $code) {
            $role = Role::create(['code' => $code, 'libelle' => $code]);
            $actor = User::factory()->create(['id_role' => $role->id]);
            $bulletin = Bulletin::create(['id_inscription' => $inscription->id, 'periode' => $code, 'moyenne' => 14]);
            $url = '/api/v1/administration/bulletins/'.$bulletin->id;
            Sanctum::actingAs($student);
            $this->getJson('/api/v1/etudiant/bulletins/'.$bulletin->id)->assertNotFound();
            Sanctum::actingAs($actor);
            $this->getJson('/api/v1/administration/bulletins?id_promotion='.$promotion->id)->assertOk();
            $this->getJson($url)->assertOk()->assertJsonPath('bulletin.id', $bulletin->id);
            $this->postJson($url.'/publier')->assertOk()->assertJsonPath('bulletin.statut', 'Publié')->assertJsonPath('bulletin.updated_by', $actor->id);
            $date = $bulletin->fresh()->date_publication->toISOString();
            $this->travel(1)->days();
            $this->postJson($url.'/publier')->assertOk()->assertJsonPath('bulletin.date_publication', $date);
            $this->travelBack();
            Sanctum::actingAs($student);
            $this->getJson('/api/v1/etudiant/bulletins/'.$bulletin->id)->assertOk();
            $bulletin->update(['statut' => 'Annulé']);
            Sanctum::actingAs($actor);
            $this->postJson($url.'/publier')->assertUnprocessable();
        }
        $this->postJson('/api/v1/administration/bulletins/99999/publier')->assertNotFound();
        $this->getJson('/api/v1/administration/bulletins?per_page=101')->assertUnprocessable();
    }

    public function test_acces_protege(): void
    {
        $base = '/api/v1/administration/bulletins';
        $this->getJson($base)->assertUnauthorized();
        $this->postJson($base.'/1/publier')->assertUnauthorized();
        foreach (['ENSEIGNANT', 'ETUDIANT', 'GESTIONNAIRE'] as $code) {
            $role = Role::create(['code' => $code, 'libelle' => $code]);
            Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));
            $this->getJson($base)->assertForbidden();
            $this->getJson($base.'/1')->assertForbidden();
            $this->postJson($base.'/1/publier')->assertForbidden();
        }
        $role = Role::create(['code' => 'ADMIN', 'libelle' => 'Admin']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id, 'is_active' => false]));
        $this->postJson($base.'/1/publier')->assertForbidden();
    }
}
