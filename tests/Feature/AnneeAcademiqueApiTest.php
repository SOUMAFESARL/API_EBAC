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

    public function test_un_libelle_supprime_peut_etre_recree_plusieurs_fois(): void
    {
        $utilisateur = $this->authentifier();
        $payload = ['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31'];
        $ids = [];

        for ($i = 0; $i < 3; $i++) {
            $id = $this->postJson('/api/v1/parametres/annees-academiques', $payload)
                ->assertCreated()->json('annee_academique.id');
            $this->assertNotContains($id, $ids);
            $ids[] = $id;
            $this->getJson('/api/v1/parametres/annees-academiques')
                ->assertOk()->assertJsonCount(1, 'annees_academiques');
            $this->postJson('/api/v1/parametres/annees-academiques', $payload)
                ->assertUnprocessable()->assertJsonValidationErrors('libelle');
            $this->deleteJson("/api/v1/parametres/annees-academiques/{$id}")->assertOk();
            $this->assertSoftDeleted('annees_academiques', [
                'id' => $id, 'libelle' => $payload['libelle'], 'deleted_by' => $utilisateur->id,
            ]);
            $this->getJson("/api/v1/parametres/annees-academiques/{$id}")->assertNotFound();
        }

        $this->assertDatabaseCount('annees_academiques', 3);
    }

    public function test_modification_du_libelle_ignore_uniquement_les_annees_supprimees(): void
    {
        $this->authentifier();
        $payload = ['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31'];
        $ancienId = $this->postJson('/api/v1/parametres/annees-academiques', $payload)
            ->assertCreated()->json('annee_academique.id');
        $id = $this->postJson('/api/v1/parametres/annees-academiques', [...$payload, 'libelle' => '2027-2028'])
            ->assertCreated()->json('annee_academique.id');

        $this->patchJson("/api/v1/parametres/annees-academiques/{$id}", ['libelle' => $payload['libelle']])
            ->assertUnprocessable()->assertJsonValidationErrors('libelle');
        $this->deleteJson("/api/v1/parametres/annees-academiques/{$ancienId}")->assertOk();
        $this->patchJson("/api/v1/parametres/annees-academiques/{$id}", ['libelle' => $payload['libelle']])
            ->assertOk()->assertJsonPath('annee_academique.libelle', $payload['libelle']);
        $this->patchJson("/api/v1/parametres/annees-academiques/{$id}", ['libelle' => $payload['libelle']])
            ->assertOk();
    }

    public function test_la_base_refuse_un_doublon_non_supprime(): void
    {
        $payload = ['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'active' => false];
        \App\Models\AnneeAcademique::query()->create($payload);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        \App\Models\AnneeAcademique::query()->create($payload);
    }

    public function test_la_suppression_ne_valide_pas_les_champs_de_modification(): void
    {
        $utilisateur = $this->authentifier();
        $id = $this->postJson('/api/v1/parametres/annees-academiques', [
            'libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31',
        ])->assertCreated()->json('annee_academique.id');

        $this->deleteJson("/api/v1/parametres/annees-academiques/{$id}", [
            'libelle' => null, 'date_debut' => null, 'date_fin' => null, 'active' => null,
        ])->assertOk();

        $this->assertSoftDeleted('annees_academiques', ['id' => $id, 'deleted_by' => $utilisateur->id]);
        $this->getJson('/api/v1/parametres/annees-academiques')->assertOk()->assertJsonCount(0, 'annees_academiques');
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
