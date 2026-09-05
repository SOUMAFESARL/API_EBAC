<?php

namespace App\Http\Controllers\Api\V1\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Administration\ModifierProfilRequest;
use App\Http\Resources\Api\V1\UtilisateurResource;
use App\Models\Etudiant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProfilController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'profil' => UtilisateurResource::make($request->user()->load(['role', 'civilite'])),
        ]);
    }

    public function edit(Request $request): JsonResponse
    {
        return response()->json([
            'profil' => UtilisateurResource::make($request->user()->load(['role', 'civilite'])),
            'champs_modifiables' => ['civilite_id', 'nom', 'prenoms', 'email', 'photo', 'password'],
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
        $etudiant = $supprimerAnciennePhoto
            ? Etudiant::query()->where('user_id', $utilisateur->id)->first()
            : null;
        $anciennePhotoIdentite = $etudiant?->photo_identite;

        if ($request->hasFile('photo')) {
            $donnees['photo'] = $request->file('photo')->store('comptes', 'public');
        }

        try {
            DB::transaction(function () use ($utilisateur, $donnees, $etudiant): void {
                $utilisateur->update([
                    ...$donnees,
                    'updated_by' => $utilisateur->id,
                ]);
                $etudiant?->update([
                    'photo_identite' => $donnees['photo'],
                    'updated_by' => $utilisateur->id,
                ]);
            });
        } catch (\Throwable $exception) {
            if ($request->hasFile('photo')) {
                Storage::disk('public')->delete($donnees['photo']);
            }
            throw $exception;
        }

        if ($supprimerAnciennePhoto) {
            Storage::disk('public')->delete(array_values(array_unique(array_filter(
                [$anciennePhoto, $anciennePhotoIdentite],
                fn ($chemin) => $chemin && $chemin !== $donnees['photo'],
            ))));
        }

        return response()->json([
            'message' => 'Profil modifié avec succès.',
            'profil' => UtilisateurResource::make($utilisateur->fresh()->load(['role', 'civilite'])),
        ]);
    }
}
