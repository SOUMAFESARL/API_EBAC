<?php

namespace App\Http\Controllers\Api\V1\Eglise;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Eglise\CreerEgliseRequest;
use App\Http\Requests\Api\V1\Eglise\ModifierEgliseRequest;
use App\Models\Eglise;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EgliseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $parPage = min(max($request->integer('par_page', 15), 1), 100);
        $recherche = $request->string('recherche')->toString();

        $eglises = Eglise::query()
            ->when($recherche, fn ($query) => $query->where(function ($query) use ($recherche) {
                $query->where('nom', 'like', "%{$recherche}%")
                    ->orWhere('sigle', 'like', "%{$recherche}%")
                    ->orWhere('code', 'like', "%{$recherche}%")
                    ->orWhere('ville_commune', 'like', "%{$recherche}%");
            }))
            ->when($request->filled('statut'), fn ($query) => $query->where('statut', $request->string('statut')))
            ->orderBy('nom')
            ->paginate($parPage)
            ->withQueryString();

        return response()->json($eglises);
    }

    public function store(CreerEgliseRequest $request): JsonResponse
    {
        $donnees = $request->validated();
        $administrateur = $request->user();

        $eglise = DB::transaction(function () use ($donnees, $administrateur) {
            $donnees['code'] = $this->genererCode();
            $donnees['user_code'] = $this->codeDuCompte($donnees['user_id'] ?? null);
            $donnees['created_by'] = $administrateur->id;

            return Eglise::query()->create($donnees);
        });

        return response()->json(['message' => 'Église créée avec succès.', 'eglise' => $eglise], 201);
    }

    public function show(Eglise $eglise): JsonResponse
    {
        return response()->json(['eglise' => $eglise->load('compte')]);
    }

    public function update(ModifierEgliseRequest $request, Eglise $eglise): JsonResponse
    {
        $donnees = $request->validated();

        if (array_key_exists('user_id', $donnees)) {
            $donnees['user_code'] = $this->codeDuCompte($donnees['user_id']);
        }

        $eglise->update([...$donnees, 'updated_by' => $request->user()->id]);

        return response()->json([
            'message' => 'Église modifiée avec succès.',
            'eglise' => $eglise->fresh(),
        ]);
    }

    public function destroy(Request $request, Eglise $eglise): JsonResponse
    {
        $eglise->update(['deleted_by' => $request->user()->id]);
        $eglise->delete();

        return response()->json(['message' => 'Église supprimée avec succès.']);
    }

    private function codeDuCompte(?int $userId): ?string
    {
        return $userId ? User::query()->whereKey($userId)->value('code') : null;
    }

    private function genererCode(): string
    {
        $dernierCode = Eglise::withTrashed()
            ->where('code', 'like', 'EGL-%')
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('code');

        $sequence = $dernierCode && preg_match('/^EGL-(\d+)$/', $dernierCode, $resultat)
            ? ((int) $resultat[1]) + 1
            : 1;

        do {
            $code = 'EGL-'.str_pad((string) $sequence++, 6, '0', STR_PAD_LEFT);
        } while (Eglise::withTrashed()->where('code', $code)->exists());

        return $code;
    }
}
