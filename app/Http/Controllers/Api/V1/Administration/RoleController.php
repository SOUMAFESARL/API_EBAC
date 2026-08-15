<?php

namespace App\Http\Controllers\Api\V1\Administration;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $roles = Role::query()
            ->with('permissions')
            ->withCount('permissions')
            ->withCount('actionsParMenu')
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
        $autorisations = $donnees['autorisations'] ?? [];
        unset($donnees['permission_ids'], $donnees['autorisations']);

        $role = DB::transaction(function () use ($donnees, $permissionIds, $autorisations, $request) {
            $role = Role::query()->create([
                ...$donnees,
                'created_by' => $request->user()->id,
            ]);
            $this->syncPermissions($role, $permissionIds, $request->user()->id);
            $this->syncAutorisations($role, $autorisations, $request->user()->id);

            return $role;
        });

        return response()->json([
            'message' => 'Rôle créé avec succès.',
            'role' => $role->load('permissions'),
            'autorisations' => $this->autorisationsDuRole($role),
        ], 201);
    }

    public function show(Role $role): JsonResponse
    {
        return response()->json([
            'role' => $role->load('permissions'),
            'autorisations' => $this->autorisationsDuRole($role),
        ]);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $donnees = $this->valider($request, $role);
        $permissionIds = $donnees['permission_ids'] ?? null;
        $autorisations = $donnees['autorisations'] ?? null;
        unset($donnees['permission_ids'], $donnees['autorisations']);

        $role->update([...$donnees, 'updated_by' => $request->user()->id]);

        if ($permissionIds !== null) {
            $this->syncPermissions($role, $permissionIds, $request->user()->id);
        }
        if ($autorisations !== null) {
            $this->syncAutorisations($role, $autorisations, $request->user()->id);
        }

        return response()->json([
            'message' => 'Rôle modifié avec succès.',
            'role' => $role->fresh()->load('permissions'),
            'autorisations' => $this->autorisationsDuRole($role),
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

    public function matriceAutorisations(): JsonResponse
    {
        return response()->json(['modules' => $this->construireMatrice()]);
    }

    public function matriceAutorisationsRole(Role $role): JsonResponse
    {
        return response()->json([
            'role' => $role,
            'modules' => $this->construireMatrice($role),
        ]);
    }

    public function synchroniserAutorisations(Request $request, Role $role): JsonResponse
    {
        $donnees = $request->validate($this->reglesAutorisations(true));
        $this->syncAutorisations($role, $donnees['autorisations'], $request->user()->id);

        return response()->json([
            'message' => 'Autorisations du rôle mises à jour.',
            'role' => $role->fresh(),
            'autorisations' => $this->autorisationsDuRole($role),
        ]);
    }

    private function valider(Request $request, ?Role $role = null): array
    {
        if ($request->has('code')) {
            $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        }

        return $request->validate([
            'code' => [$role ? 'sometimes' : 'required', 'string', 'max:30', 'alpha_dash:ascii', Rule::unique('roles', 'code')->ignore($role)],
            'libelle' => [$role ? 'sometimes' : 'required', 'string', 'max:80'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'actif' => ['sometimes', 'boolean'],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['integer', 'distinct', 'exists:permissions,id'],
            ...$this->reglesAutorisations(),
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

    private function reglesAutorisations(bool $obligatoire = false): array
    {
        return [
            'autorisations' => [$obligatoire ? 'present' : 'sometimes', 'array'],
            'autorisations.*.menu_id' => ['required', 'integer', 'distinct', 'exists:menus,id'],
            'autorisations.*.action_ids' => ['present', 'array'],
            'autorisations.*.action_ids.*' => ['integer', 'distinct', 'exists:actions,id'],
        ];
    }

    private function syncAutorisations(Role $role, array $autorisations, int $userId): void
    {
        $lignes = [];
        foreach ($autorisations as $index => $autorisation) {
            $actionsDisponibles = DB::table('menu_actions')
                ->where('id_menu', $autorisation['menu_id'])
                ->pluck('id_action')
                ->map(fn ($id) => (int) $id)
                ->all();
            $actionsInvalides = array_diff($autorisation['action_ids'], $actionsDisponibles);
            if ($actionsInvalides !== []) {
                throw ValidationException::withMessages([
                    "autorisations.{$index}.action_ids" => ['Une ou plusieurs actions ne sont pas disponibles pour ce menu.'],
                ]);
            }
            foreach ($autorisation['action_ids'] as $actionId) {
                $lignes[] = [
                    'id_role' => $role->id,
                    'id_menu' => $autorisation['menu_id'],
                    'id_action' => $actionId,
                    'created_by' => $userId,
                    'created_at' => now(),
                ];
            }
        }

        DB::transaction(function () use ($role, $lignes) {
            DB::table('role_menu_actions')->where('id_role', $role->id)->delete();
            if ($lignes !== []) {
                DB::table('role_menu_actions')->insert($lignes);
            }
        });
    }

    private function construireMatrice(?Role $role = null): array
    {
        $selection = $role
            ? DB::table('role_menu_actions')->where('id_role', $role->id)->get()->groupBy('id_menu')
            : collect();

        return Menu::query()
            ->with(['actions' => fn ($query) => $query->where('actif', true)->orderBy('libelle')])
            ->where('actif', true)
            ->orderBy('ordre')
            ->orderBy('libelle')
            ->get()
            ->map(fn (Menu $menu) => [
                'menu_id' => $menu->id,
                'code' => $menu->code,
                'libelle' => $menu->libelle,
                'groupe' => $menu->groupe,
                'actions' => $menu->actions->map(fn ($action) => [
                    'id' => $action->id,
                    'code' => $action->code,
                    'libelle' => $action->libelle,
                    'selectionnee' => $selection->get($menu->id)?->contains('id_action', $action->id) ?? false,
                ])->values(),
            ])->values()->all();
    }

    private function autorisationsDuRole(Role $role): array
    {
        return DB::table('role_menu_actions as rma')
            ->join('menus as m', 'm.id', '=', 'rma.id_menu')
            ->join('actions as a', 'a.id', '=', 'rma.id_action')
            ->where('rma.id_role', $role->id)
            ->orderBy('m.ordre')
            ->orderBy('a.libelle')
            ->get(['m.id as menu_id', 'm.code as menu_code', 'm.libelle as menu_libelle', 'a.id as action_id', 'a.code as action_code', 'a.libelle as action_libelle'])
            ->groupBy('menu_id')
            ->map(fn ($lignes) => [
                'menu_id' => $lignes->first()->menu_id,
                'menu_code' => $lignes->first()->menu_code,
                'menu_libelle' => $lignes->first()->menu_libelle,
                'actions' => $lignes->map(fn ($ligne) => [
                    'id' => $ligne->action_id,
                    'code' => $ligne->action_code,
                    'libelle' => $ligne->action_libelle,
                ])->values(),
            ])->values()->all();
    }

}
