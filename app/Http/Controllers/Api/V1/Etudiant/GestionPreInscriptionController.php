<?php

namespace App\Http\Controllers\Api\V1\Etudiant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UtilisateurResource;
use App\Models\AnneeAcademique;
use App\Models\Etudiant;
use App\Models\Role;
use App\Models\User;
use App\Notifications\CompteCreeNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class GestionPreInscriptionController extends Controller
{
    #[OA\Get(path: '/administration/preinscriptions', operationId: 'listerPreinscriptionsAdministration', summary: 'Lister les préinscriptions à vérifier', tags: ['Administration des préinscriptions'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'statut', in: 'query', schema: new OA\Schema(type: 'string', default: 'Préinscrit')), new OA\Parameter(name: 'recherche', in: 'query', schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'par_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 15))], responses: [new OA\Response(response: 200, description: 'Liste paginée', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/PreInscriptionAdministration'))])), new OA\Response(response: 401, description: 'Non authentifié'), new OA\Response(response: 403, description: 'Permission COMPTE_GERER requise')])]
    public function index(Request $request): AnonymousResourceCollection
    {
        $parPage = min(max($request->integer('par_page', 15), 1), 100);

        $preinscriptions = Etudiant::query()
            ->with(['eglise', 'dossier.fichiers'])
            ->where('statut', $request->string('statut', 'Préinscrit')->toString())
            ->when($request->string('recherche')->toString(), function ($query, string $recherche) {
                $query->where(function ($query) use ($recherche) {
                    $query->where('nom', 'like', "%{$recherche}%")
                        ->orWhere('prenoms', 'like', "%{$recherche}%")
                        ->orWhere('email', 'like', "%{$recherche}%")
                        ->orWhere('matricule', 'like', "%{$recherche}%")
                        ->orWhereHas('dossier', fn ($query) => $query->where('numero_dossier', 'like', "%{$recherche}%"));
                });
            })
            ->latest('id')
            ->paginate($parPage)
            ->withQueryString()
            ->through(fn (Etudiant $etudiant) => $this->formatter($etudiant));

        return JsonResource::collection($preinscriptions);
    }

    #[OA\Get(path: '/administration/preinscriptions/{id}', operationId: 'afficherPreinscriptionAdministration', summary: 'Consulter une préinscription', tags: ['Administration des préinscriptions'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Détail de la préinscription', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'preinscription', ref: '#/components/schemas/PreInscriptionAdministration')])), new OA\Response(response: 401, description: 'Non authentifié'), new OA\Response(response: 403, description: 'Permission COMPTE_GERER requise'), new OA\Response(response: 404, description: 'Préinscription introuvable')])]
    public function show(int $id): JsonResponse
    {
        $preinscription = Etudiant::query()->findOrFail($id);

        return response()->json([
            'preinscription' => $this->formatter($preinscription->load(['eglise', 'dossier.fichiers'])),
        ]);
    }

    #[OA\Post(path: '/administration/preinscriptions/{id}/creer-compte', operationId: 'creerCompteDepuisPreinscription', summary: 'Valider une préinscription et créer automatiquement le compte étudiant', description: 'Attribue le rôle ETUDIANT sans choix côté client, rattache le compte à la fiche, valide le dossier et envoie par e-mail le mot de passe temporaire, le matricule, l’année académique et l’église.', tags: ['Administration des préinscriptions'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Compte étudiant créé et e-mail envoyé', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'message', type: 'string'), new OA\Property(property: 'compte', ref: '#/components/schemas/CompteEtudiantCree')])), new OA\Response(response: 401, description: 'Non authentifié'), new OA\Response(response: 403, description: 'Permission COMPTE_GERER requise'), new OA\Response(response: 404, description: 'Préinscription introuvable'), new OA\Response(response: 422, description: 'Préinscription déjà traitée, e-mail déjà utilisé, rôle absent ou aucune année académique active')])]
    public function valider(Request $request, int $id): JsonResponse
    {
        $preinscription = Etudiant::query()->findOrFail($id);
        $motDePasseTemporaire = Str::password(16);
        $createur = $request->user();
        $anneeAcademique = AnneeAcademique::query()->where('active', true)->first();

        if (! $anneeAcademique) {
            return response()->json([
                'message' => 'Aucune année académique active n’est configurée.',
            ], 422);
        }

        $preinscription->loadMissing('eglise');

        $compte = DB::transaction(function () use ($preinscription, $motDePasseTemporaire, $createur): User {
            $etudiant = Etudiant::query()->lockForUpdate()->findOrFail($preinscription->id);

            if ($etudiant->statut !== 'Préinscrit' || $etudiant->user_id !== null) {
                abort(422, 'Cette préinscription a déjà été traitée.');
            }

            if (User::withTrashed()->where('email', $etudiant->email)->exists()) {
                abort(422, 'Un compte utilise déjà l’adresse e-mail de cette préinscription.');
            }

            $roleEtudiant = Role::query()->where('code', 'ETUDIANT')->first();
            if (! $roleEtudiant) {
                abort(422, 'Le rôle ETUDIANT n’est pas configuré.');
            }

            $compte = User::query()->create([
                'civilite_id' => $etudiant->civilite_id,
                'matricule' => $etudiant->matricule,
                'code' => $this->genererCode(),
                'user_code' => $createur->code,
                'user_id' => (string) $createur->id,
                'nom' => $etudiant->nom,
                'prenoms' => $etudiant->prenoms,
                'photo' => $etudiant->photo_identite,
                'email' => $etudiant->email,
                'password' => $motDePasseTemporaire,
                'id_role' => $roleEtudiant->id,
                'is_active' => true,
                'statut' => 'Actif',
                'deux_fa_active' => false,
                'created_by' => $createur->id,
            ]);

            $etudiant->update([
                'user_id' => $compte->id,
                'statut' => 'Inscrit',
                'updated_by' => $createur->id,
            ]);
            $etudiant->dossier?->update([
                'user_id' => $compte->id,
                'statut' => 'Validé',
                'updated_by' => $createur->id,
            ]);

            return $compte;
        });

        $compte->notifyNow(new CompteCreeNotification(
            motDePasseTemporaire: $motDePasseTemporaire,
            anneeAcademique: $anneeAcademique->libelle,
            eglise: $preinscription->eglise?->nom ?? 'Non renseignée',
        ));

        return response()->json([
            'message' => 'Préinscription validée. Le compte étudiant a été créé et ses identifiants ont été envoyés par email.',
            'compte' => UtilisateurResource::make($compte->load(['role', 'civilite'])),
        ]);
    }

    /** @return array<string, mixed> */
    private function formatter(Etudiant $etudiant): array
    {
        return [
            'id' => $etudiant->id,
            'user_id' => $etudiant->user_id,
            'matricule' => $etudiant->matricule,
            'nom' => $etudiant->nom,
            'prenoms' => $etudiant->prenoms,
            'email' => $etudiant->email,
            'telephone' => $etudiant->telephone,
            'civilite_id' => $etudiant->civilite_id,
            'date_naissance' => $etudiant->date_naissance?->format('Y-m-d'),
            'lieu_naissance' => $etudiant->lieu_naissance,
            'nationalite' => $etudiant->nationalite,
            'adresse' => $etudiant->adresse,
            'statut_professionnel' => $etudiant->statut_professionnel,
            'photo_identite' => $etudiant->photo_identite,
            'date_inscription' => $etudiant->date_inscription?->format('Y-m-d'),
            'statut' => $etudiant->statut,
            'eglise' => $etudiant->relationLoaded('eglise') ? $etudiant->eglise : null,
            'dossier' => $etudiant->relationLoaded('dossier') ? $etudiant->dossier : null,
        ];
    }

    private function genererCode(): string
    {
        $derniereSequence = User::withTrashed()
            ->where('code', 'like', 'USR-%')
            ->lockForUpdate()
            ->pluck('code')
            ->map(fn (?string $code): int => preg_match('/^USR-(\d+)$/', (string) $code, $matches) ? (int) $matches[1] : 0)
            ->max() ?? 0;

        return 'USR-'.str_pad((string) ($derniereSequence + 1), 6, '0', STR_PAD_LEFT);
    }
}
