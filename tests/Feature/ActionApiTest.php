<?php

namespace Tests\Feature;

use App\Models\Action;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_administrateur_liste_et_attribue_les_actions_a_une_permission(): void
    {
        $role = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));

        $this->getJson('/api/v1/administration/actions')
            ->assertOk()
            ->assertJsonCount(6, 'actions')
            ->assertJsonFragment(['code' => 'AJOUTER'])
            ->assertJsonFragment(['code' => 'SUPPRIMER'])
            ->assertJsonFragment(['code' => 'MODIFIER'])
            ->assertJsonFragment(['code' => 'VOIR'])
            ->assertJsonFragment(['code' => 'IMPRIMER'])
            ->assertJsonFragment(['code' => 'TELECHARGER']);

        $permissionId = $this->postJson('/api/v1/administration/permissions', [
            'libelle' => 'Gestion des étudiants',
        ])->assertCreated()->json('permission.id');

        $actionIds = Action::query()->whereIn('code', ['AJOUTER', 'MODIFIER', 'VOIR'])->pluck('id')->all();

        $this->putJson("/api/v1/administration/permissions/{$permissionId}/actions", [
            'action_ids' => $actionIds,
        ])->assertOk()
            ->assertJsonCount(3, 'permission.actions');

        foreach ($actionIds as $actionId) {
            $this->assertDatabaseHas('permission_actions', [
                'id_permission' => $permissionId,
                'id_action' => $actionId,
            ]);
        }
    }
}
