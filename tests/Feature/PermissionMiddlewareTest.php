<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_non_admin_avec_la_permission_peut_acceder_au_module(): void
    {
        $role = Role::query()->create(['code' => 'ROL-000001', 'libelle' => 'Gestionnaire']);
        $permission = Permission::query()->create([
            'code' => 'ROLE_GERER',
            'libelle' => 'Gérer les rôles',
        ]);
        $role->permissions()->attach($permission->id, ['actif' => true]);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));

        $this->getJson('/api/v1/administration/roles')->assertOk();
    }

    public function test_un_non_admin_sans_permission_recoit_une_erreur_403(): void
    {
        $role = Role::query()->create(['code' => 'ROL-000001', 'libelle' => 'Utilisateur']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));

        $this->getJson('/api/v1/administration/menus')
            ->assertForbidden()
            ->assertJsonPath('permissions_requises.0', 'MENU_GERER');
    }

    public function test_un_admin_conserve_un_acces_total_sans_permission_explicitement_attribuee(): void
    {
        $role = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));

        $this->getJson('/api/v1/administration/roles')->assertOk();
        $this->getJson('/api/v1/administration/permissions')->assertOk();
        $this->getJson('/api/v1/administration/menus')->assertOk();
        $this->getJson('/api/v1/administration/comptes')->assertOk();
    }

    public function test_les_roles_etudiant_et_enseignant_ne_peuvent_pas_acceder_aux_comptes_meme_avec_la_permission(): void
    {
        $permission = Permission::query()->create([
            'code' => 'COMPTE_GERER',
            'libelle' => 'Gérer les comptes',
        ]);

        foreach (['ETUDIANT', 'ENSEIGNANT'] as $codeRole) {
            $role = Role::query()->create([
                'code' => $codeRole,
                'libelle' => $codeRole,
            ]);
            $role->permissions()->attach($permission->id, ['actif' => true]);
            Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));

            $this->getJson('/api/v1/administration/comptes')->assertForbidden();
        }
    }
}
