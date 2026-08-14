<?php

namespace App\Http\Controllers\Api\V1\Administration;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $roles = Role::query()
            ->with('permissions')
            ->withCount('permissions')
            ->when($request->string('recherche')->toString(), function ($query, string $recherche) {
                $query->where(fn ($query) => $query
                    ->where('code', 'like', "%{$recherche}%")
                    ->orWhere('libelle', 'like', "%{$recherche}%"));
            })
            ->orderBy('libelle')
            ->paginate(min(max($request->integer('par_page', 15), 1), 100));

        return response()->json($roles);
    }

    public function store(Request $request): JsonResponse
    {
        $donnees = $this->valider($request);
        $permissionIds = $donnees['permission_ids'] ?? [];
        unset($donnees['permission_ids']);

        $role = DB::transaction(function () use ($donnees, $permissionIds, $request) {
            $role = Role::query()->create([
                ...$donnees,
                'code' => $this->genererCode(),
                'created_by' => $request->user()->id,
            ]);
            $this->syncPermissions($role, $permissionIds, $request->user()->id);

            return $role;
        });

        return response()->json([
            'message' => 'Rôle créé avec succès.',
            'role' => $role->load('permissions'),
        ], 201);
    }

    public function show(Role $role): JsonResponse
    {
        return response()->json(['role' => $role->load('permissions')]);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $donnees = $this->valider($request, $role);
        $permissionIds = $donnees['permission_ids'] ?? null;
        unset($donnees['permission_ids']);

        $role->update([...$donnees, 'updated_by' => $request->user()->id]);

        if ($permissionIds !== null) {
            $this->syncPermissions($role, $permissionIds, $request->user()->id);
        }

        return response()->json([
            'message' => 'Rôle modifié avec succès.',
            'role' => $role->fresh()->load('permissions'),
        ]);
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        if ($role->code === 'ADMIN') {
            return response()->json(['message' => 'Le rôle administrateur ne peut pas être supprimé.'], 422);
        }
        if ($role->users()->exists()) {
            return response()->json(['message' => 'Ce rôle est encore attribué à des comptes.'], 422);
        }

        $role->update(['deleted_by' => $request->user()->id]);
        $role->delete();

        return response()->json(['message' => 'Rôle supprimé avec succès.']);
    }

    public function synchroniserPermissions(Request $request, Role $role): JsonResponse
    {
        $donnees = $request->validate([
            'permission_ids' => ['present', 'array'],
            'permission_ids.*' => ['integer', 'distinct', 'exists:permissions,id'],
        ]);
        $this->syncPermissions($role, $donnees['permission_ids'], $request->user()->id);

        return response()->json([
            'message' => 'Permissions du rôle mises à jour.',
            'role' => $role->fresh()->load('permissions'),
        ]);
    }

    private function valider(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'code' => ['prohibited'],
            'libelle' => [$role ? 'sometimes' : 'required', 'string', 'max:80'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['integer', 'distinct', 'exists:permissions,id'],
        ]);
    }

    private function syncPermissions(Role $role, array $permissionIds, int $userId): void
    {
        $role->permissions()->syncWithPivotValues($permissionIds, [
            'actif' => true,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    private function genererCode(): string
    {
        $dernierCode = Role::withTrashed()
            ->where('code', 'like', 'ROL-%')
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('code');

        $prochaineSequence = $dernierCode && preg_match('/^ROL-(\d+)$/', $dernierCode, $correspondances)
            ? ((int) $correspondances[1]) + 1
            : 1;

        do {
            $code = 'ROL-'.str_pad((string) $prochaineSequence, 6, '0', STR_PAD_LEFT);
            $prochaineSequence++;
        } while (Role::withTrashed()->where('code', $code)->exists());

        return $code;
    }
}
