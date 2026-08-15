<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RolePermissionMenuWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_administrateur_cree_une_permission_un_role_et_un_menu_associes(): void
    {
        $roleAdministrateur = Role::query()->create([
            'code' => 'ADMIN',
            'libelle' => 'Administrateur',
        ]);

        Sanctum::actingAs(User::factory()->create([
            'id_role' => $roleAdministrateur->id,
        ]));

        $permissionId = $this->postJson('/api/v1/administration/permissions', [
            'libelle' => 'Consulter les étudiants',
            'description' => 'Autorise la consultation des étudiants.',
        ])->assertCreated()
            ->assertJsonPath('permission.code', 'PER-000001')
            ->json('permission.id');

        $roleId = $this->postJson('/api/v1/administration/roles', [
            'libelle' => 'Gestionnaire des étudiants',
            'description' => 'Gère les informations des étudiants.',
            'permission_ids' => [$permissionId],
        ])->assertCreated()
            ->assertJsonPath('role.code', 'ROL-000001')
            ->assertJsonPath('role.permissions.0.id', $permissionId)
            ->json('role.id');

        $menuId = $this->postJson('/api/v1/administration/menus', [
            'libelle' => 'Étudiants',
            'description' => 'Accès à la gestion des étudiants.',
            'route' => '/etudiants',
            'route_active' => '/etudiants*',
            'icone' => 'users',
            'groupe' => 'Scolarité',
            'ordre' => 10,
            'visible' => true,
            'actif' => true,
            'permission_ids' => [$permissionId],
        ])->assertCreated()
            ->assertJsonPath('menu.code', 'MEN-000001')
            ->assertJsonPath('menu.permissions.0.id', $permissionId)
            ->json('menu.id');

        $this->assertDatabaseHas('role_permissions', [
            'id_role' => $roleId,
            'id_permission' => $permissionId,
        ]);
        $this->assertDatabaseHas('menu_permissions', [
            'id_menu' => $menuId,
            'id_permission' => $permissionId,
        ]);

        $this->getJson('/api/v1/administration/roles')
            ->assertOk()
            ->assertJsonFragment(['libelle' => 'Gestionnaire des étudiants']);

        $this->getJson('/api/v1/administration/menus')
            ->assertOk()
            ->assertJsonFragment(['libelle' => 'Étudiants']);
    }
}
