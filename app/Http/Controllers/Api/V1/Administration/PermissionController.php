<?php

namespace App\Http\Controllers\Api\V1\Administration;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $permission = DB::transaction(fn () => Permission::query()->create([
            ...$donnees,
            'code' => $this->genererCode(),
            'created_by' => $request->user()->id,
        ]));

        return response()->json([
            'message' => 'Permission créée avec succès.',
            'permission' => $permission,
        ], 201);
    }

    public function show(Permission $permission): JsonResponse
    {
        return response()->json(['permission' => $permission->load('roles', 'actions')]);
    }

    public function update(Request $request, Permission $permission): JsonResponse
    {
        $donnees = $this->valider($request, $permission);
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

    public function synchroniserActions(Request $request, Permission $permission): JsonResponse
    {
        $donnees = $request->validate([
            'action_ids' => ['present', 'array'],
            'action_ids.*' => ['integer', 'distinct', 'exists:actions,id'],
        ]);
        $permission->actions()->syncWithPivotValues($donnees['action_ids'], [
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Actions de la permission mises à jour.',
            'permission' => $permission->fresh()->load('actions'),
        ]);
    }

    private function valider(Request $request, ?Permission $permission = null): array
    {
        return $request->validate([
            'code' => ['prohibited'],
            'libelle' => [$permission ? 'sometimes' : 'required', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);
    }

    private function genererCode(): string
    {
        $dernierCode = Permission::withTrashed()
            ->where('code', 'like', 'PER-%')
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('code');

        $sequence = $dernierCode && preg_match('/^PER-(\d+)$/', $dernierCode, $correspondances)
            ? ((int) $correspondances[1]) + 1
            : 1;

        do {
            $code = 'PER-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
            $sequence++;
        } while (Permission::withTrashed()->where('code', $code)->exists());

        return $code;
    }
}
