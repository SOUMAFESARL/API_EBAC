<?php

namespace App\Http\Controllers\Api\V1\Navigation;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SidebarController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $utilisateur = $request->user()->loadMissing('role.permissions');
        $estAdministrateur = $utilisateur->role?->code === 'ADMIN';
        $permissionIds = $utilisateur->role?->permissions
            ->where('pivot.actif', true)
            ->pluck('id')
            ->all() ?? [];

        $tousLesMenus = Menu::query()
            ->with('permissions:id')
            ->where('actif', true)
            ->where('visible', true)
            ->orderBy('ordre')
            ->orderBy('libelle')
            ->get();

        $menus = $tousLesMenus->filter(fn (Menu $menu) => $estAdministrateur
                || $menu->permissions->isEmpty()
                || $menu->permissions->pluck('id')->intersect($permissionIds)->isNotEmpty());

        $idsAutorises = $menus->pluck('id')->all();
        foreach ($menus as $menu) {
            $parentId = $menu->id_parent;
            while ($parentId !== null) {
                $parent = $tousLesMenus->firstWhere('id', $parentId);
                if (! $parent) {
                    break;
                }
                $idsAutorises[] = $parent->id;
                $parentId = $parent->id_parent;
            }
        }
        $menus = $tousLesMenus->whereIn('id', array_unique($idsAutorises));

        return response()->json([
            'sidebar' => $this->construireArbre($menus),
        ]);
    }

    private function construireArbre(Collection $menus, ?int $parentId = null): array
    {
        return $menus
            ->where('id_parent', $parentId)
            ->map(function (Menu $menu) use ($menus) {
                $enfants = $this->construireArbre($menus, $menu->id);

                return [
                    'id' => $menu->id,
                    'code' => $menu->code,
                    'libelle' => $menu->libelle,
                    'route' => $menu->route,
                    'route_active' => $menu->route_active,
                    'icone' => $menu->icone,
                    'groupe' => $menu->groupe,
                    'ordre' => $menu->ordre,
                    'enfants' => $enfants,
                ];
            })
            ->values()
            ->all();
    }
}
