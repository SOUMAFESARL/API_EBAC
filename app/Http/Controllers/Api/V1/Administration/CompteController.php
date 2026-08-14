<?php

namespace App\Http\Controllers\Api\V1\Administration;

use App\DTOs\Api\V1\Administration\CreerCompteDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Administration\EnregistrerCompteRequest;
use App\Http\Requests\Api\V1\Administration\ModifierCompteRequest;
use App\Http\Resources\Api\V1\UtilisateurResource;
use App\Models\Civilite;
use App\Models\Role;
use App\Models\User;
use App\Notifications\CompteCreeNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CompteController extends Controller
{
    public function create(): JsonResponse
    {
        return response()->json([
            'roles' => Role::query()
                ->select(['id', 'code', 'libelle', 'portee'])
                ->orderBy('libelle')
                ->get(),
            'civilites' => Civilite::query()
                ->where('actif', true)
                ->select(['id', 'code', 'name', 'abreviation'])
                ->orderBy('name')
                ->get(),
            'statuts' => ['Actif', 'Suspendu', 'Bloqué', 'Désactivé'],
            'valeurs_par_defaut' => [
                'is_active' => true,
                'statut' => 'Actif',
                'deux_fa_active' => false,
            ],
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $parPage = min(max($request->integer('par_page', 15), 1), 100);

        $comptes = User::query()
            ->with(['role', 'civilite'])
            ->when($request->string('recherche')->toString(), function ($query, string $recherche) {
                $query->where(function ($query) use ($recherche) {
                    $query->where('nom', 'like', "%{$recherche}%")
                        ->orWhere('prenoms', 'like', "%{$recherche}%")
                        ->orWhere('email', 'like', "%{$recherche}%")
                        ->orWhere('matricule', 'like', "%{$recherche}%")
                        ->orWhere('code', 'like', "%{$recherche}%");
                });
            })
            ->when($request->filled('statut'), fn ($query) => $query->where('statut', $request->string('statut')))
            ->when($request->filled('id_role'), fn ($query) => $query->where('id_role', $request->integer('id_role')))
            ->latest('id')
            ->paginate($parPage)
            ->withQueryString();

        return UtilisateurResource::collection($comptes);
    }

    public function store(EnregistrerCompteRequest $request): JsonResponse
    {
        $donnees = $request->validated();
        $motDePasseTemporaire = Str::password(16);
        $administrateur = $request->user();

        if ($request->hasFile('photo')) {
            $donnees['photo'] = $request->file('photo')->store('comptes', 'public');
        }

        $compte = DB::transaction(function () use ($donnees, $motDePasseTemporaire, $administrateur) {
            $matricule = $this->genererMatricule($donnees['nom'], $donnees['prenoms']);
            $donnees['code'] = $this->genererCode();

            $dto = CreerCompteDTO::fromArray(
                $donnees,
                $motDePasseTemporaire,
                $matricule,
                $administrateur,
            );

            return User::query()->create($dto->toArray());
        });

        $compte->notifyNow(new CompteCreeNotification($motDePasseTemporaire));

        return response()->json([
            'message' => 'Compte créé avec succès. Le mot de passe temporaire a été envoyé par email.',
            'compte' => UtilisateurResource::make($compte->load(['role', 'civilite'])),
        ], 201);
    }

    public function show(User $compte): JsonResponse
    {
        return response()->json([
            'compte' => UtilisateurResource::make($compte->load(['role', 'civilite'])),
        ]);
    }

    public function edit(User $compte): JsonResponse
    {
        return response()->json([
            'compte' => UtilisateurResource::make($compte->load(['role', 'civilite'])),
            'roles' => Role::query()
                ->select(['id', 'code', 'libelle', 'portee'])
                ->orderBy('libelle')
                ->get(),
            'civilites' => Civilite::query()
                ->where('actif', true)
                ->select(['id', 'code', 'name', 'abreviation'])
                ->orderBy('name')
                ->get(),
            'statuts' => ['Actif', 'Suspendu', 'Bloqué', 'Désactivé'],
        ]);
    }

    public function update(ModifierCompteRequest $request, User $compte): JsonResponse
    {
        $donnees = $request->validated();
        unset($donnees['password_confirmation']);

        $anciennePhoto = $compte->photo;
        $supprimerAnciennePhoto = $request->hasFile('photo')
            || ($request->exists('photo') && $request->input('photo') === null);

        if ($request->hasFile('photo')) {
            $donnees['photo'] = $request->file('photo')->store('comptes', 'public');
        }

        $compte->update([
            ...$donnees,
            'updated_by' => $request->user()->id,
        ]);

        if ($supprimerAnciennePhoto && $anciennePhoto) {
            Storage::disk('public')->delete($anciennePhoto);
        }

        return response()->json([
            'message' => 'Compte modifié avec succès.',
            'compte' => UtilisateurResource::make($compte->fresh()->load(['role', 'civilite'])),
        ]);
    }

    public function destroy(Request $request, User $compte): JsonResponse
    {
        if ($request->user()->is($compte)) {
            return response()->json([
                'message' => 'Vous ne pouvez pas supprimer votre propre compte.',
            ], 422);
        }

        $compte->forceFill(['deleted_by' => $request->user()->id])->save();
        $compte->delete();

        return response()->json([
            'message' => 'Compte supprimé avec succès.',
        ]);
    }

    private function genererMatricule(string $nom, string $prenoms): string
    {
        $nomNormalise = preg_replace('/[^A-Z]/', '', Str::upper(Str::ascii($nom))) ?: '';
        $prenomNormalise = preg_replace('/[^A-Z]/', '', Str::upper(Str::ascii($prenoms))) ?: '';

        $prefixeNom = str_pad(substr($nomNormalise, 0, 3), 3, 'X');
        $prefixePrenom = str_pad(substr($prenomNormalise, 0, 2), 2, 'X');
        $prefixe = sprintf('EBAC-%s-%s-%s-', $prefixeNom, $prefixePrenom, now()->year);

        $dernierMatricule = User::withTrashed()
            ->where('matricule', 'like', $prefixe.'%')
            ->lockForUpdate()
            ->max('matricule');

        $prochaineSequence = $dernierMatricule
            ? ((int) substr($dernierMatricule, -4)) + 1
            : 1;

        return $prefixe.str_pad((string) $prochaineSequence, 4, '0', STR_PAD_LEFT);
    }

    private function genererCode(): string
    {
        $dernierCode = User::withTrashed()
            ->where('code', 'like', 'USR-%')
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('code');

        $prochaineSequence = $dernierCode && preg_match('/^USR-(\d+)$/', $dernierCode, $correspondances)
            ? ((int) $correspondances[1]) + 1
            : 1;

        do {
            $code = 'USR-'.str_pad((string) $prochaineSequence, 6, '0', STR_PAD_LEFT);
            $prochaineSequence++;
        } while (User::withTrashed()->where('code', $code)->exists());

        return $code;
    }
}
