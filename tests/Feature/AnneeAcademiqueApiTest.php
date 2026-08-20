<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnneeAcademiqueApiTest extends TestCase
{
    use RefreshDatabase;

    private function authentifier(): User
    {
        $role = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        $utilisateur = User::factory()->create(['id_role' => $role->id]);
        Sanctum::actingAs($utilisateur);

        return $utilisateur;
    }

    public function test_crud_d_une_annee_academique(): void
    {
        $utilisateur = $this->authentifier();

        $id = $this->postJson('/api/v1/parametres/annees-academiques', [
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'active' => true,
        ])->assertCreated()
            ->assertJsonPath('annee_academique.active', true)
            ->assertJsonPath('annee_academique.created_by', $utilisateur->id)
            ->json('annee_academique.id');

        $this->getJson('/api/v1/parametres/annees-academiques')
            ->assertOk()->assertJsonCount(1, 'annees_academiques');
        $this->getJson("/api/v1/parametres/annees-academiques/{$id}")
            ->assertOk()->assertJsonPath('annee_academique.libelle', '2026-2027');
        $this->patchJson("/api/v1/parametres/annees-academiques/{$id}", ['active' => false])
            ->assertOk()->assertJsonPath('annee_academique.active', false);
        $this->deleteJson("/api/v1/parametres/annees-academiques/{$id}")
            ->assertOk();

        $this->assertSoftDeleted('annees_academiques', ['id' => $id, 'deleted_by' => $utilisateur->id]);
    }

    public function test_dates_et_libelle_sont_valides(): void
    {
        $this->authentifier();
        $payload = ['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31'];
        $this->postJson('/api/v1/parametres/annees-academiques', $payload)->assertCreated();
        $this->postJson('/api/v1/parametres/annees-academiques', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors(['libelle']);
        $this->postJson('/api/v1/parametres/annees-academiques', [
            'libelle' => '2027-2028', 'date_debut' => '2028-07-31', 'date_fin' => '2027-09-01',
        ])->assertUnprocessable()->assertJsonValidationErrors(['date_fin']);
    }
}
