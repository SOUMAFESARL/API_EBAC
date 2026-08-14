<?php

namespace App\Http\Controllers\Api\V1\Administration;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MenuController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'menus' => Menu::query()->with('permissions:id,code,libelle')->orderBy('ordre')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $donnees = $this->valider($request);
        $permissionIds = $donnees['permission_ids'] ?? [];
        unset($donnees['permission_ids']);
        $menu = Menu::query()->create([
            ...$donnees,
            'code' => strtoupper($donnees['code']),
            'created_by' => $request->user()->id,
        ]);
        $menu->permissions()->sync($permissionIds);

        return response()->json([
            'message' => 'Menu créé avec succès.',
            'menu' => $menu->load('permissions'),
        ], 201);
    }

    public function show(Menu $menu): JsonResponse
    {
        return response()->json(['menu' => $menu->load('permissions', 'enfants')]);
    }

    public function update(Request $request, Menu $menu): JsonResponse
    {
        $donnees = $this->valider($request, $menu);
        $permissionIds = $donnees['permission_ids'] ?? null;
        unset($donnees['permission_ids']);
        if (isset($donnees['code'])) {
            $donnees['code'] = strtoupper($donnees['code']);
        }
        $menu->update([...$donnees, 'updated_by' => $request->user()->id]);
        if ($permissionIds !== null) {
            $menu->permissions()->sync($permissionIds);
        }

        return response()->json([
            'message' => 'Menu modifié avec succès.',
            'menu' => $menu->fresh()->load('permissions'),
        ]);
    }

    public function destroy(Request $request, Menu $menu): JsonResponse
    {
        $menu->update(['deleted_by' => $request->user()->id]);
        $menu->delete();

        return response()->json(['message' => 'Menu supprimé avec succès.']);
    }

    private function valider(Request $request, ?Menu $menu = null): array
    {
        return $request->validate([
            'id_parent' => ['sometimes', 'nullable', 'integer', 'exists:menus,id', Rule::notIn([$menu?->id])],
            'code' => [$menu ? 'sometimes' : 'required', 'string', 'max:100', Rule::unique('menus')->ignore($menu)],
            'libelle' => [$menu ? 'sometimes' : 'required', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'route' => ['sometimes', 'nullable', 'string', 'max:180'],
            'route_active' => ['sometimes', 'nullable', 'string', 'max:180'],
            'icone' => ['sometimes', 'nullable', 'string', 'max:100'],
            'groupe' => ['sometimes', 'nullable', 'string', 'max:100'],
            'ordre' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'visible' => ['sometimes', 'boolean'],
            'actif' => ['sometimes', 'boolean'],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['integer', 'distinct', 'exists:permissions,id'],
        ]);
    }
}
