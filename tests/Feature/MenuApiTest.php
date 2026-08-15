<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MenuApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_administrateur_peut_creer_modifier_et_supprimer_des_menus(): void
    {
        $role = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));

        $permissionId = $this->postJson('/api/v1/administration/permissions', [
            'libelle' => 'Voir les étudiants',
        ])->assertCreated()
            ->assertJsonPath('permission.code', 'PER-000001')
            ->json('permission.id');

        $parentId = $this->postJson('/api/v1/administration/menus', [
            'libelle' => 'Scolarité',
            'icone' => 'school',
            'groupe' => 'Scolarité',
            'ordre' => 10,
        ])->assertCreated()
            ->assertJsonPath('menu.code', 'MEN-000001')
            ->json('menu.id');

        $menuId = $this->postJson('/api/v1/administration/menus', [
            'id_parent' => $parentId,
            'libelle' => 'Étudiants',
            'route' => '/scolarite/etudiants',
            'route_active' => '/scolarite/etudiants*',
            'icone' => 'users',
            'ordre' => 20,
            'visible' => true,
            'actif' => true,
            'permission_ids' => [$permissionId],
        ])->assertCreated()
            ->assertJsonPath('menu.code', 'MEN-000002')
            ->assertJsonPath('menu.id_parent', $parentId)
            ->assertJsonPath('menu.permissions.0.id', $permissionId)
            ->json('menu.id');

        $this->getJson("/api/v1/administration/menus/{$menuId}")
            ->assertOk()
            ->assertJsonPath('menu.route', '/scolarite/etudiants');

        $this->patchJson("/api/v1/administration/menus/{$menuId}", [
            'libelle' => 'Gestion des étudiants',
            'ordre' => 25,
        ])->assertOk()
            ->assertJsonPath('menu.libelle', 'Gestion des étudiants')
            ->assertJsonPath('menu.ordre', 25);

        $this->getJson('/api/v1/administration/menus')
            ->assertOk()
            ->assertJsonCount(2, 'menus');

        $this->deleteJson("/api/v1/administration/menus/{$menuId}")->assertOk();
        $this->assertSoftDeleted('menus', ['id' => $menuId]);
    }

    public function test_le_frontend_ne_peut_pas_imposer_le_code_d_un_menu(): void
    {
        $role = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));

        $this->postJson('/api/v1/administration/menus', [
            'code' => 'CODE_FRONTEND',
            'libelle' => 'Menu interdit',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }
}
