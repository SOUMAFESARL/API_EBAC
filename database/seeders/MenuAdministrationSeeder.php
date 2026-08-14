<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class MenuAdministrationSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            'COMPTE_GERER' => ['Gérer les comptes', '/administration/comptes', 'users', 10],
            'ROLE_GERER' => ['Gérer les rôles', '/administration/roles', 'shield', 20],
            'PERMISSION_GERER' => ['Gérer les permissions', '/administration/permissions', 'key', 30],
            'MENU_GERER' => ['Gérer les menus', '/administration/menus', 'menu', 40],
        ];

        $parent = Menu::query()->firstOrCreate(
            ['code' => 'ADMINISTRATION'],
            ['libelle' => 'Administration', 'icone' => 'settings', 'groupe' => 'Administration', 'ordre' => 10],
        );

        $permissionIds = [];
        foreach ($definitions as $code => [$libelle, $route, $icone, $ordre]) {
            $permission = Permission::query()->firstOrCreate(
                ['code' => $code],
                ['libelle' => $libelle, 'description' => $libelle],
            );
            $menu = Menu::query()->firstOrCreate(
                ['code' => $code],
                [
                    'id_parent' => $parent->id,
                    'libelle' => $libelle,
                    'route' => $route,
                    'route_active' => $route.'*',
                    'icone' => $icone,
                    'groupe' => 'Administration',
                    'ordre' => $ordre,
                ],
            );
            $menu->permissions()->syncWithoutDetaching([$permission->id => ['permission_principale' => true]]);
            $permissionIds[] = $permission->id;
        }

        Role::query()->where('code', 'ADMIN')->first()?->permissions()
            ->syncWithoutDetaching(array_fill_keys($permissionIds, ['actif' => true]));
    }
}
