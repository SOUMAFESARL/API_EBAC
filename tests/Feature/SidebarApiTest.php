<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SidebarApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_sidebar_est_filtree_selon_les_permissions_du_role(): void
    {
        $role = Role::query()->create(['code' => 'GEST', 'libelle' => 'Gestionnaire']);
        $autorisee = Permission::query()->create(['code' => 'COMPTES_VOIR', 'libelle' => 'Voir comptes']);
        $interdite = Permission::query()->create(['code' => 'ROLES_VOIR', 'libelle' => 'Voir rôles']);
        $role->permissions()->attach($autorisee->id, ['actif' => true]);
        $parent = Menu::query()->create(['code' => 'ADMINISTRATION', 'libelle' => 'Administration']);
        $menuAutorise = Menu::query()->create([
            'id_parent' => $parent->id, 'code' => 'COMPTES', 'libelle' => 'Comptes', 'route' => '/comptes',
        ]);
        $menuInterdit = Menu::query()->create([
            'id_parent' => $parent->id, 'code' => 'ROLES', 'libelle' => 'Rôles', 'route' => '/roles',
        ]);
        $menuAutorise->permissions()->attach($autorisee->id);
        $menuInterdit->permissions()->attach($interdite->id);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));

        $this->getJson('/api/v1/navigation/sidebar')
            ->assertOk()
            ->assertJsonPath('sidebar.0.code', 'ADMINISTRATION')
            ->assertJsonPath('sidebar.0.enfants.0.code', 'COMPTES')
            ->assertJsonMissing(['code' => 'ROLES']);
    }
}
