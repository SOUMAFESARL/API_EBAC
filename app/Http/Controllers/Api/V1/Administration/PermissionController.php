<?php

namespace App\Http\Controllers\Api\V1\Administration;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PermissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $permissions = Permission::query()
            ->when($request->string('recherche')->toString(), function ($query, string $recherche) {
                $query->where(fn ($query) => $query
                    ->where('code', 'like', "%{$recherche}%")
                    ->orWhere('libelle', 'like', "%{$recherche}%"));
            })
            ->orderBy('libelle')
            ->paginate(min(max($request->integer('par_page', 15), 1), 100));

        return response()->json($permissions);
    }

    public function store(Request $request): JsonResponse
    {
        $donnees = $this->valider($request);
        $permission = Permission::query()->create([
            ...$donnees,
            'code' => strtoupper($donnees['code']),
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Permission créée avec succès.',
            'permission' => $permission,
        ], 201);
    }

    public function show(Permission $permission): JsonResponse
    {
        return response()->json(['permission' => $permission->load('roles')]);
    }

    public function update(Request $request, Permission $permission): JsonResponse
    {
        $donnees = $this->valider($request, $permission);
        if (isset($donnees['code'])) {
            $donnees['code'] = strtoupper($donnees['code']);
        }
        $permission->update([...$donnees, 'updated_by' => $request->user()->id]);

        return response()->json([
            'message' => 'Permission modifiée avec succès.',
            'permission' => $permission->fresh(),
        ]);
    }

    public function destroy(Request $request, Permission $permission): JsonResponse
    {
        if ($permission->roles()->exists()) {
            return response()->json([
                'message' => 'Cette permission est encore affectée à un ou plusieurs rôles.',
            ], 422);
        }

        $permission->update(['deleted_by' => $request->user()->id]);
        $permission->delete();

        return response()->json(['message' => 'Permission supprimée avec succès.']);
    }

    private function valider(Request $request, ?Permission $permission = null): array
    {
        return $request->validate([
            'code' => [$permission ? 'sometimes' : 'required', 'string', 'max:30', Rule::unique('permissions')->ignore($permission)],
            'libelle' => [$permission ? 'sometimes' : 'required', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);
    }
}
