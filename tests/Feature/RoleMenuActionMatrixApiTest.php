<?php

namespace Tests\Feature;

use App\Models\Action;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleMenuActionMatrixApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_administrateur_configure_un_role_avec_une_matrice_menu_actions(): void
    {
        $admin = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $admin->id]));

        $actions = Action::query()->whereIn('code', ['VOIR', 'AJOUTER', 'MODIFIER', 'SUPPRIMER'])->get()->keyBy('code');
        $menuId = $this->postJson('/api/v1/administration/menus', [
            'libelle' => 'Étudiants',
            'route' => '/etudiants',
            'ordre' => 10,
            'visible' => true,
            'actif' => true,
            'action_ids' => $actions->pluck('id')->all(),
        ])->assertCreated()
            ->assertJsonCount(4, 'menu.actions')
            ->json('menu.id');

        $selection = [$actions['VOIR']->id, $actions['AJOUTER']->id, $actions['MODIFIER']->id];
        $roleId = $this->postJson('/api/v1/administration/roles', [
            'code' => 'GESTIONNAIRE_ETUDIANTS',
            'libelle' => 'Gestionnaire des étudiants',
            'description' => 'Gestion des dossiers étudiants',
            'actif' => true,
            'autorisations' => [
                ['menu_id' => $menuId, 'action_ids' => $selection],
            ],
        ])->assertCreated()
            ->assertJsonPath('role.code', 'GESTIONNAIRE_ETUDIANTS')
            ->assertJsonPath('role.actif', true)
            ->assertJsonCount(1, 'autorisations')
            ->json('role.id');

        foreach ($selection as $actionId) {
            $this->assertDatabaseHas('role_menu_actions', [
                'id_role' => $roleId,
                'id_menu' => $menuId,
                'id_action' => $actionId,
            ]);
        }

        $this->getJson("/api/v1/administration/roles/{$roleId}/matrice-autorisations")
            ->assertOk()
            ->assertJsonPath('modules.0.menu_id', $menuId)
            ->assertJsonCount(4, 'modules.0.actions');

        $this->putJson("/api/v1/administration/roles/{$roleId}/autorisations", [
            'autorisations' => [
                ['menu_id' => $menuId, 'action_ids' => [$actions['VOIR']->id]],
            ],
        ])->assertOk()
            ->assertJsonCount(1, 'autorisations.0.actions');

        Sanctum::actingAs(User::factory()->create(['id_role' => $roleId]));
        $this->getJson('/api/v1/navigation/sidebar')
            ->assertOk()
            ->assertJsonPath('sidebar.0.code', 'MEN-000001')
            ->assertJsonPath('sidebar.0.action_ids.0', $actions['VOIR']->id);
    }

    public function test_une_action_non_disponible_sur_le_menu_est_refusee(): void
    {
        $admin = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $admin->id]));
        $voir = Action::query()->where('code', 'VOIR')->firstOrFail();
        $ajouter = Action::query()->where('code', 'AJOUTER')->firstOrFail();

        $menuId = $this->postJson('/api/v1/administration/menus', [
            'libelle' => 'Tableau de bord',
            'action_ids' => [$voir->id],
        ])->assertCreated()->json('menu.id');

        $this->postJson('/api/v1/administration/roles', [
            'code' => 'ROLE_INVALIDE',
            'libelle' => 'Rôle invalide',
            'autorisations' => [
                ['menu_id' => $menuId, 'action_ids' => [$ajouter->id]],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('autorisations.0.action_ids');
    }
}
