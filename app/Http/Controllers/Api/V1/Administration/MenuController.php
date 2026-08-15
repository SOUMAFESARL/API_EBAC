<?php

namespace App\Http\Controllers\Api\V1\Administration;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $menu = DB::transaction(function () use ($donnees, $permissionIds, $request) {
            $menu = Menu::query()->create([
                ...$donnees,
                'code' => $this->genererCode(),
                'created_by' => $request->user()->id,
            ]);
            $menu->permissions()->sync($permissionIds);

            return $menu;
        });

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
            'code' => ['prohibited'],
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

    private function genererCode(): string
    {
        $dernierCode = Menu::withTrashed()
            ->where('code', 'like', 'MEN-%')
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('code');

        $sequence = $dernierCode && preg_match('/^MEN-(\d+)$/', $dernierCode, $correspondances)
            ? ((int) $correspondances[1]) + 1
            : 1;

        do {
            $code = 'MEN-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
            $sequence++;
        } while (Menu::withTrashed()->where('code', $code)->exists());

        return $code;
    }
}
