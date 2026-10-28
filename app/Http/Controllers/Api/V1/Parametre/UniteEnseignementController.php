<?php

namespace App\Http\Controllers\Api\V1\Parametre;

use App\DTOs\Api\V1\Parametre\UniteEnseignementDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Parametre\CreerUniteEnseignementRequest;
use App\Http\Requests\Api\V1\Parametre\ModifierUniteEnseignementRequest;
use App\Models\UniteEnseignement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UniteEnseignementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filtres = $request->validate([
            'statut' => ['sometimes', 'required', Rule::in(['Actif', 'Archive'])],
            'code' => ['sometimes', 'nullable', 'string', 'max:50'],
            'q' => ['sometimes', 'nullable', 'string', 'max:180'],
            'recherche' => ['sometimes', 'nullable', 'string', 'max:180'],
        ]);
        $terme = $filtres['recherche'] ?? $filtres['q'] ?? null;
        $unites = UniteEnseignement::query()
            ->when($filtres['statut'] ?? null, fn ($query, $statut) => $query->where('statut', $statut))
            ->when($request->filled('code'), fn ($query) => $query->where('code', $filtres['code']))
            ->when($terme !== null && $terme !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('libelle', 'like', "%{$terme}%")
                ->orWhere('code', 'like', "%{$terme}%")))
            ->orderBy('libelle')->get();

        return response()->json(['unites_enseignement' => $unites]);
    }

    public function store(CreerUniteEnseignementRequest $request): JsonResponse
    {
        $dto = UniteEnseignementDTO::fromArray($request->validated());
        $unite = UniteEnseignement::query()->create([
            ...$dto->toArray(),
            'user_id' => $request->user()->id,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Unité d’enseignement créée avec succès.', 'unite_enseignement' => $unite->fresh()], 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['unite_enseignement' => UniteEnseignement::query()->findOrFail($id)]);
    }

    public function update(ModifierUniteEnseignementRequest $request, int $id): JsonResponse
    {
        $unite = UniteEnseignement::query()->findOrFail($id);
        $dto = UniteEnseignementDTO::fromArray($request->validated());
        $unite->update([...$dto->toArray(), 'updated_by' => $request->user()->id]);

        return response()->json(['message' => 'Unité d’enseignement modifiée avec succès.', 'unite_enseignement' => $unite->fresh()]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $unite = UniteEnseignement::query()->findOrFail($id);
        $unite->getConnection()->transaction(function () use ($unite, $request) {
            $unite->update(['deleted_by' => $request->user()->id]);
            $unite->delete();
        });

        return response()->json(['message' => 'Unité d’enseignement supprimée avec succès.']);
    }
}
