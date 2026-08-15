<?php

namespace App\Http\Controllers\Api\V1\Administration;

use App\Http\Controllers\Controller;
use App\Models\Action;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ActionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'actions' => Action::query()->withCount('permissions')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $donnees = $this->valider($request);
        $action = Action::query()->create([
            ...$donnees,
            'code' => $this->genererCode($donnees['libelle']),
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Action créée avec succès.', 'action' => $action], 201);
    }

    public function show(Action $action): JsonResponse
    {
        return response()->json(['action' => $action->load('permissions')]);
    }

    public function update(Request $request, Action $action): JsonResponse
    {
        $action->update([...$this->valider($request, true), 'updated_by' => $request->user()->id]);

        return response()->json(['message' => 'Action modifiée avec succès.', 'action' => $action->fresh()]);
    }

    public function destroy(Request $request, Action $action): JsonResponse
    {
        if ($action->permissions()->exists()) {
            return response()->json(['message' => 'Cette action est encore attribuée à une permission.'], 422);
        }

        $action->update(['deleted_by' => $request->user()->id]);
        $action->delete();

        return response()->json(['message' => 'Action supprimée avec succès.']);
    }

    private function valider(Request $request, bool $modification = false): array
    {
        return $request->validate([
            'code' => ['prohibited'],
            'libelle' => [$modification ? 'sometimes' : 'required', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'actif' => ['sometimes', 'boolean'],
        ]);
    }

    private function genererCode(string $libelle): string
    {
        $base = Str::of($libelle)->ascii()->upper()->replaceMatches('/[^A-Z0-9]+/', '_')->trim('_')->limit(24, '')->toString();
        $base = $base !== '' ? $base : 'ACTION';
        $code = $base;
        $suffixe = 2;

        while (Action::withTrashed()->where('code', $code)->exists()) {
            $code = Str::limit($base, 25, '').'_'.$suffixe++;
        }

        return $code;
    }
}
