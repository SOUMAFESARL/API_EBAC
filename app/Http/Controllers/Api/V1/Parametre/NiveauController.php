<?php

namespace App\Http\Controllers\Api\V1\Parametre;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Parametre\CreerNiveauRequest;
use App\Http\Requests\Api\V1\Parametre\ModifierNiveauRequest;
use App\Models\Niveau;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class NiveauController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'niveaux' => Niveau::query()->orderBy('rang')->get(),
        ]);
    }

    public function store(CreerNiveauRequest $request): JsonResponse
    {
        $utilisateur = $request->user();
        $niveau = Niveau::query()->create([
            ...$request->validated(),
            'user_id' => $utilisateur->id,
            'user_code' => $utilisateur->user_code,
            'created_by' => $utilisateur->id,
        ]);

        return response()->json([
            'message' => 'Niveau créé avec succès.',
            'niveau' => $niveau,
        ], 201);
    }

    public function show(Niveau $niveau): JsonResponse
    {
        return response()->json(['niveau' => $niveau]);
    }

    public function update(ModifierNiveauRequest $request, Niveau $niveau): JsonResponse
    {
        $niveau->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Niveau modifié avec succès.',
            'niveau' => $niveau->fresh(),
        ]);
    }

    public function destroy(ModifierNiveauRequest $request, Niveau $niveau): JsonResponse
    {
        if (DB::table('promotions')->where('id_niveau', $niveau->id)->exists()) {
            return response()->json([
                'message' => 'Ce niveau est utilisé par une promotion et ne peut pas être supprimé.',
            ], 422);
        }

        $niveau->update(['deleted_by' => $request->user()->id]);
        $niveau->delete();

        return response()->json(['message' => 'Niveau supprimé avec succès.']);
    }
}
