<?php

namespace App\Http\Controllers\Api\V1\Navigation;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SidebarController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $utilisateur = $request->user()->loadMissing('role.permissions');
        $estAdministrateur = $utilisateur->role?->code === 'ADMIN';
        if (! $estAdministrateur && ! ($utilisateur->role?->actif ?? true)) {
            return response()->json(['sidebar' => []]);
        }
        $actionsParMenu = $utilisateur->role
            ? DB::table('role_menu_actions')->where('id_role', $utilisateur->role->id)->get()->groupBy('id_menu')
            : collect();
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

        $utiliseMatrice = $actionsParMenu->isNotEmpty();
        $menus = $tousLesMenus->filter(fn (Menu $menu) => $estAdministrateur
                || ($utiliseMatrice && $actionsParMenu->has($menu->id))
                || (! $utiliseMatrice && ($menu->permissions->isEmpty()
                || $menu->permissions->pluck('id')->intersect($permissionIds)->isNotEmpty())));

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
            'sidebar' => $this->construireArbre($menus, null, $actionsParMenu, $estAdministrateur),
        ]);
    }

    private function construireArbre(Collection $menus, ?int $parentId, Collection $actionsParMenu, bool $estAdministrateur): array
    {
        return $menus
            ->where('id_parent', $parentId)
            ->map(function (Menu $menu) use ($menus, $actionsParMenu, $estAdministrateur) {
                $enfants = $this->construireArbre($menus, $menu->id, $actionsParMenu, $estAdministrateur);

                return [
                    'id' => $menu->id,
                    'code' => $menu->code,
                    'libelle' => $menu->libelle,
                    'route' => $menu->route,
                    'route_active' => $menu->route_active,
                    'icone' => $menu->icone,
                    'groupe' => $menu->groupe,
                    'ordre' => $menu->ordre,
                    'action_ids' => $estAdministrateur
                        ? $menu->actions()->where('actif', true)->pluck('actions.id')->all()
                        : ($actionsParMenu->get($menu->id)?->pluck('id_action')->values()->all() ?? []),
                    'enfants' => $enfants,
                ];
            })
            ->values()
            ->all();
    }
}
