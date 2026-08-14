<?php

namespace App\Http\Controllers\Api\V1\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Administration\ModifierProfilRequest;
use App\Http\Resources\Api\V1\UtilisateurResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfilController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'profil' => UtilisateurResource::make($request->user()->load('role')),
        ]);
    }

    public function edit(Request $request): JsonResponse
    {
        return response()->json([
            'profil' => UtilisateurResource::make($request->user()->load('role')),
            'champs_modifiables' => ['nom', 'prenoms', 'email', 'photo', 'password'],
        ]);
    }

    public function update(ModifierProfilRequest $request): JsonResponse
    {
        $utilisateur = $request->user();
        $donnees = $request->validated();
        unset($donnees['mot_de_passe_actuel'], $donnees['password_confirmation']);

        $anciennePhoto = $utilisateur->photo;
        $supprimerAnciennePhoto = $request->hasFile('photo')
            || ($request->exists('photo') && $request->input('photo') === null);

        if ($request->hasFile('photo')) {
            $donnees['photo'] = $request->file('photo')->store('comptes', 'public');
        }

        $utilisateur->update([
            ...$donnees,
            'updated_by' => $utilisateur->id,
        ]);

        if ($supprimerAnciennePhoto && $anciennePhoto) {
            Storage::disk('public')->delete($anciennePhoto);
        }

        return response()->json([
            'message' => 'Profil modifié avec succès.',
            'profil' => UtilisateurResource::make($utilisateur->fresh()->load('role')),
        ]);
    }
}
