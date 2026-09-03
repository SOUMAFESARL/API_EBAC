<?php

namespace App\Http\Controllers\Api\V1\Etudiant;

use App\Http\Controllers\Controller;
use App\Models\AnneeAcademique;
use App\Models\Etudiant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class EtudiantController extends Controller
{
    #[OA\Get(path: '/administration/etudiants', operationId: 'listerTousLesEtudiants', summary: 'Lister tous les étudiants et préinscrits', tags: ['Administration des étudiants'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'recherche', in: 'query', schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'statut', in: 'query', description: 'Exemple : Préinscrit ou Inscrit', schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'compte_cree', in: 'query', schema: new OA\Schema(type: 'boolean')), new OA\Parameter(name: 'avec_dossier', in: 'query', schema: new OA\Schema(type: 'boolean')), new OA\Parameter(name: 'dossier_statut', in: 'query', schema: new OA\Schema(type: 'string', example: 'Incomplet')), new OA\Parameter(name: 'niveau_id', in: 'query', schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'promotion_id', in: 'query', schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'annee_entree', in: 'query', schema: new OA\Schema(type: 'integer', example: 2026)), new OA\Parameter(name: 'annee_academique_id', in: 'query', schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'eglise_id', in: 'query', schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'civilite_id', in: 'query', schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'date', in: 'query', description: 'Date exacte de préinscription ou inscription', schema: new OA\Schema(type: 'string', format: 'date')), new OA\Parameter(name: 'date_debut', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')), new OA\Parameter(name: 'date_fin', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')), new OA\Parameter(name: 'par_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 15))], responses: [new OA\Response(response: 200, description: 'Liste paginée des étudiants', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/EtudiantListe'))])), new OA\Response(response: 401, description: 'Non authentifié'), new OA\Response(response: 403, description: 'Accès interdit aux rôles ENSEIGNANT et ETUDIANT'), new OA\Response(response: 422, description: 'Filtres invalides')])]
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtres = $request->validate([
            'recherche' => ['sometimes', 'string', 'max:150'],
            'statut' => ['sometimes', 'string', 'max:50'],
            'compte_cree' => ['sometimes', 'boolean'],
            'avec_dossier' => ['sometimes', 'boolean'],
            'dossier_statut' => ['sometimes', 'string', 'max:30'],
            'niveau_id' => ['sometimes', 'integer', Rule::exists('niveaux', 'id')->whereNull('deleted_at')],
            'promotion_id' => ['sometimes', 'integer', Rule::exists('promotions', 'id')->whereNull('deleted_at')],
            'annee_entree' => ['sometimes', 'integer', 'min:1900', 'max:9999'],
            'annee_academique_id' => ['sometimes', 'integer', Rule::exists('annees_academiques', 'id')->whereNull('deleted_at')],
            'eglise_id' => ['sometimes', 'integer', Rule::exists('eglises', 'id')->whereNull('deleted_at')],
            'civilite_id' => ['sometimes', 'integer', 'exists:civilite,id'],
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'date_debut' => ['sometimes', 'date_format:Y-m-d'],
            'date_fin' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:date_debut'],
            'par_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $anneeAcademique = isset($filtres['annee_academique_id'])
            ? AnneeAcademique::query()->findOrFail($filtres['annee_academique_id'])
            : null;
        $anneesAcademiques = AnneeAcademique::query()->orderByDesc('date_debut')->get();

        $etudiants = Etudiant::query()
            ->with(['eglise', 'civilite', 'user.role', 'dossier', 'inscriptions.promotion.niveau'])
            ->when($filtres['recherche'] ?? null, function ($query, string $recherche) {
                $query->where(function ($query) use ($recherche) {
                    $query->where('nom', 'like', "%{$recherche}%")
                        ->orWhere('prenoms', 'like', "%{$recherche}%")
                        ->orWhere('email', 'like', "%{$recherche}%")
                        ->orWhere('telephone', 'like', "%{$recherche}%")
                        ->orWhere('matricule', 'like', "%{$recherche}%");
                });
            })
            ->when($filtres['statut'] ?? null, fn ($query, string $statut) => $query->where('statut', $statut))
            ->when(array_key_exists('compte_cree', $filtres), fn ($query) => $filtres['compte_cree'] ? $query->whereNotNull('user_id') : $query->whereNull('user_id'))
            ->when(array_key_exists('avec_dossier', $filtres), fn ($query) => $filtres['avec_dossier'] ? $query->whereHas('dossier') : $query->whereDoesntHave('dossier'))
            ->when($filtres['dossier_statut'] ?? null, fn ($query, string $statut) => $query->whereHas('dossier', fn ($query) => $query->where('statut', $statut)))
            ->when($filtres['promotion_id'] ?? null, fn ($query, int $promotionId) => $query->whereHas('inscriptions', fn ($query) => $query->where('id_promotion', $promotionId)))
            ->when($filtres['niveau_id'] ?? null, fn ($query, int $niveauId) => $query->whereHas('inscriptions.promotion', fn ($query) => $query->where('id_niveau', $niveauId)))
            ->when($filtres['annee_entree'] ?? null, fn ($query, int $annee) => $query->whereHas('inscriptions.promotion', fn ($query) => $query->where('annee_entree', $annee)))
            ->when($filtres['eglise_id'] ?? null, fn ($query, int $egliseId) => $query->where('eglise_id', $egliseId))
            ->when($filtres['civilite_id'] ?? null, fn ($query, int $civiliteId) => $query->where('civilite_id', $civiliteId))
            ->when($filtres['date'] ?? null, fn ($query, string $date) => $query->whereDate('date_inscription', $date))
            ->when($filtres['date_debut'] ?? null, fn ($query, string $date) => $query->whereDate('date_inscription', '>=', $date))
            ->when($filtres['date_fin'] ?? null, fn ($query, string $date) => $query->whereDate('date_inscription', '<=', $date))
            ->when($anneeAcademique, fn ($query) => $query->whereBetween('date_inscription', [
                $anneeAcademique->date_debut->toDateString(),
                $anneeAcademique->date_fin->toDateString(),
            ]))
            ->latest('date_inscription')
            ->latest('id')
            ->paginate($filtres['par_page'] ?? 15)
            ->withQueryString()
            ->through(function (Etudiant $etudiant) use ($anneesAcademiques): array {
                $annee = $anneesAcademiques->first(fn (AnneeAcademique $annee) => $etudiant->date_inscription->betweenIncluded($annee->date_debut, $annee->date_fin));

                return [
                    'id' => $etudiant->id,
                    'user_id' => $etudiant->user_id,
                    'matricule' => $etudiant->matricule,
                    'nom' => $etudiant->nom,
                    'prenoms' => $etudiant->prenoms,
                    'email' => $etudiant->email,
                    'telephone' => $etudiant->telephone,
                    'date_inscription' => $etudiant->date_inscription->format('Y-m-d'),
                    'statut' => $etudiant->statut,
                    'annee_academique' => $annee,
                    'eglise' => $etudiant->eglise,
                    'civilite' => $etudiant->civilite,
                    'compte' => $etudiant->user,
                    'dossier' => $etudiant->dossier,
                    'inscriptions' => $etudiant->inscriptions,
                ];
            });

        return JsonResource::collection($etudiants);
    }
}
