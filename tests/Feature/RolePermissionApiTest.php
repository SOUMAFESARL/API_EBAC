<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RolePermissionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_administrateur_peut_gerer_roles_et_permissions(): void
    {
        $adminRole = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        $admin = User::factory()->create(['id_role' => $adminRole->id]);
        Sanctum::actingAs($admin);

        $permissionId = $this->postJson('/api/v1/administration/permissions', [
            'libelle' => 'Voir les comptes',
        ])->assertCreated()
            ->assertJsonPath('permission.code', 'PER-000001')
            ->json('permission.id');

        $roleId = $this->postJson('/api/v1/administration/roles', [
            'code' => 'GESTIONNAIRE',
            'libelle' => 'Gestionnaire',
            'permission_ids' => [$permissionId],
        ])->assertCreated()
            ->assertJsonPath('role.code', 'GESTIONNAIRE')
            ->assertJsonPath('role.permissions.0.id', $permissionId)
            ->json('role.id');

        $this->getJson("/api/v1/administration/roles/{$roleId}")->assertOk();
        $this->putJson("/api/v1/administration/roles/{$roleId}/permissions", [
            'permission_ids' => [],
        ])->assertOk()->assertJsonCount(0, 'role.permissions');
        $this->deleteJson("/api/v1/administration/permissions/{$permissionId}")->assertOk();
        $this->deleteJson("/api/v1/administration/roles/{$roleId}")->assertOk();
    }

    public function test_un_non_administrateur_ne_peut_pas_acceder_aux_roles(): void
    {
        $role = Role::query()->create(['code' => 'USER', 'libelle' => 'Utilisateur']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));

        $this->getJson('/api/v1/administration/roles')->assertForbidden();
    }

    public function test_le_code_d_une_permission_est_genere_par_l_api_et_ne_peut_pas_etre_impose(): void
    {
        $adminRole = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $adminRole->id]));

        $this->postJson('/api/v1/administration/permissions', [
            'code' => 'CODE_FRONTEND',
            'libelle' => 'Permission interdite',
        ])->assertUnprocessable()->assertJsonValidationErrors('code');

        $permissionId = $this->postJson('/api/v1/administration/permissions', [
            'libelle' => 'Exporter les étudiants',
        ])->assertCreated()
            ->assertJsonPath('permission.code', 'PER-000001')
            ->json('permission.id');

        $this->patchJson("/api/v1/administration/permissions/{$permissionId}", [
            'libelle' => 'Exporter la liste des étudiants',
        ])->assertOk()
            ->assertJsonPath('permission.code', 'PER-000001')
            ->assertJsonPath('permission.libelle', 'Exporter la liste des étudiants');
    }

    public function test_le_code_du_role_est_saisi_par_le_frontend_normalise_et_unique(): void
    {
        $adminRole = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $adminRole->id]));

        $this->postJson('/api/v1/administration/roles', [
            'libelle' => 'Sans code',
        ])->assertUnprocessable()->assertJsonValidationErrors('code');

        $this->postJson('/api/v1/administration/roles', [
            'code' => 'gestionnaire-etudiants',
            'libelle' => 'Gestionnaire étudiants',
        ])->assertCreated()
            ->assertJsonPath('role.code', 'GESTIONNAIRE-ETUDIANTS');

        $this->postJson('/api/v1/administration/roles', [
            'code' => 'GESTIONNAIRE-ETUDIANTS',
            'libelle' => 'Doublon',
        ])->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    public function test_une_case_a_cocher_enregistre_immediatement_le_droit_du_role(): void
    {
        $adminRole = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        $gestionnaire = Role::query()->create([
            'code' => 'GESTIONNAIRE',
            'libelle' => 'Gestionnaire',
            'description' => 'Gestion des opérations',
        ]);
        $permission = Permission::query()->create([
            'code' => 'COMPTE_GERER',
            'libelle' => 'Gérer les comptes',
            'description' => 'Gérer les comptes',
        ]);
        Sanctum::actingAs(User::factory()->create(['id_role' => $adminRole->id]));

        $this->getJson('/api/v1/administration/roles/catalogue-droits')
            ->assertOk()
            ->assertJsonPath('nombre_roles', 2)
            ->assertJsonPath('nombre_droits', 1);

        $url = "/api/v1/administration/roles/{$gestionnaire->id}/droits/{$permission->id}";
        $this->patchJson($url, ['accordee' => true])
            ->assertOk()
            ->assertJsonPath('accordee', true);
        $this->assertDatabaseHas('role_permissions', [
            'id_role' => $gestionnaire->id,
            'id_permission' => $permission->id,
            'actif' => true,
        ]);

        $this->getJson("/api/v1/administration/roles/{$gestionnaire->id}/droits")
            ->assertOk()
            ->assertJsonPath('droits.0.accordee', true);

        $this->patchJson($url, ['accordee' => false])
            ->assertOk()
            ->assertJsonPath('accordee', false);
        $this->assertDatabaseHas('role_permissions', [
            'id_role' => $gestionnaire->id,
            'id_permission' => $permission->id,
            'actif' => false,
        ]);
    }
}
