<?php

namespace App\Http\Controllers\Api\V1\Parametre;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Parametre\EnregistrerSalleRequest;
use App\Models\Salle;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SalleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'recherche' => ['nullable', 'string', 'max:180'],
            'statut' => ['sometimes', Rule::in(['Actif', 'Inactif'])],
            'tri' => ['sometimes', Rule::in(['code', 'nom', 'statut'])],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $query = Salle::query();
        $recherche = $data['recherche'] ?? null;
        if ($recherche !== null && $recherche !== '') {
            $recherche = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($recherche)).'%';
            $query->where(fn ($q) => $q->whereRaw("LOWER(nom) LIKE ? ESCAPE '!'", [$recherche])
                ->orWhereRaw("LOWER(code) LIKE ? ESCAPE '!'", [$recherche]));
        }
        if (isset($data['statut'])) {
            $query->where('statut', $data['statut']);
        }
        $salles = $query->orderBy($data['tri'] ?? 'code', $data['direction'] ?? 'asc')->orderBy('id')
            ->paginate($data['per_page'] ?? 15);

        return response()->json([
            'salles' => $salles->getCollection()->map(fn ($salle) => $this->presenter($salle))->values(),
            'salles_en_service' => Salle::query()->where('statut', 'Actif')->count(),
            'meta' => ['current_page' => $salles->currentPage(), 'last_page' => $salles->lastPage(),
                'per_page' => $salles->perPage(), 'total' => $salles->total(), 'from' => $salles->firstItem(), 'to' => $salles->lastItem()],
        ]);
    }

    public function store(EnregistrerSalleRequest $request): JsonResponse
    {
        try {
            $salle = Salle::query()->create([...$request->validated(), 'statut' => $request->validated('statut', 'Actif'), 'created_by' => $request->user()->id]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => ['Ce code est déjà utilisé par une salle.']]);
        }

        return response()->json(['message' => 'Salle créée avec succès.', 'salle' => $this->presenter($salle)], 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['salle' => $this->presenter(Salle::query()->findOrFail($id))]);
    }

    public function update(EnregistrerSalleRequest $request, int $id): JsonResponse
    {
        $salle = Salle::query()->findOrFail($id);
        try {
            $salle->update([...$request->validated(), 'updated_by' => $request->user()->id]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => ['Ce code est déjà utilisé par une salle.']]);
        }

        return response()->json(['message' => 'Salle modifiée avec succès.', 'salle' => $this->presenter($salle->fresh())]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        DB::transaction(function () use ($request, $id) {
            $salle = Salle::query()->lockForUpdate()->findOrFail($id);
            $salle->update(['deleted_by' => $request->user()->id]);
            $salle->delete();
        });

        return response()->json(['message' => 'Salle supprimée avec succès.']);
    }

    private function presenter(Salle $salle): array
    {
        return [...$salle->toArray(), 'seances_par_semaine' => null];
    }
}
