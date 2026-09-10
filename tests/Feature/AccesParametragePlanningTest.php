<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccesParametragePlanningTest extends TestCase
{
    use RefreshDatabase;

    private function connecter(string $code): void
    {
        $role = Role::firstOrCreate(['code' => $code], ['libelle' => $code]);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));
    }

    public function test_enseignants_et_etudiants_interdits_sur_toutes_les_operations(): void
    {
        foreach (['ENSEIGNANT', 'ETUDIANT', 'SECRETARIAT', 'AUTRE'] as $role) {
            $this->connecter($role);
            foreach (['salles', 'creneaux', 'annees-academiques'] as $resource) {
                $url = '/api/v1/parametres/'.$resource;
                $this->getJson($url)->assertForbidden();
                $this->postJson($url, [])->assertForbidden();
                $this->getJson($url.'/1')->assertForbidden();
                $this->putJson($url.'/1', [])->assertForbidden();
                $this->patchJson($url.'/1', [])->assertForbidden();
                $this->deleteJson($url.'/1')->assertForbidden();
            }
            $this->getJson('/api/v1/parametres/creneaux/options')->assertForbidden();
            $url = '/api/v1/parametres/annees-academiques/1/calendrier';
            $this->getJson($url)->assertForbidden();
            $this->postJson($url, [])->assertForbidden();
            $this->putJson($url, [])->assertForbidden();
            $this->deleteJson($url)->assertForbidden();
        }
    }

    public function test_roles_administratifs_autorises(): void
    {
        $annee = AnneeAcademique::create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-30']);
        foreach (['ADMIN', 'SECRETAIRE_ACADEMIQUE', 'DIRECTION'] as $role) {
            $this->connecter($role);
            $this->getJson('/api/v1/parametres/annees-academiques')->assertOk();
            $this->getJson('/api/v1/parametres/salles')->assertOk();
            $this->getJson('/api/v1/parametres/creneaux?id_annee_academique='.$annee->id)->assertOk();
            $this->getJson('/api/v1/parametres/creneaux/options?id_annee_academique='.$annee->id)->assertOk();
            $url = '/api/v1/parametres/annees-academiques/'.$annee->id.'/calendrier';
            $this->postJson($url, ['modules' => [['libelle' => 'Module', 'date_debut' => '2026-09-01', 'date_fin' => '2027-06-30', 'examens' => [], 'rattrapages' => []]], 'jours_feries' => [], 'conges' => [], 'grandes_vacances' => null])->assertCreated();
            $this->getJson($url)->assertOk();
            $this->deleteJson($url)->assertOk();
            $id = $this->postJson('/api/v1/parametres/salles', ['nom' => 'Salle', 'code' => $role])->assertCreated()->json('salle.id');
            $this->patchJson('/api/v1/parametres/salles/'.$id, ['statut' => 'Inactif'])->assertOk();
            $this->deleteJson('/api/v1/parametres/salles/'.$id)->assertOk();
        }
    }
}
