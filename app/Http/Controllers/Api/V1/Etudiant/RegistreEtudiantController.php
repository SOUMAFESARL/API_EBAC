<?php

namespace App\Http\Controllers\Api\V1\Etudiant;

use App\Http\Controllers\Controller;
use App\Models\Etudiant;
use App\Models\Inscription;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class RegistreEtudiantController extends Controller
{
    #[OA\Get(path: '/administration/registre-etudiants', operationId: 'listerRegistreEtudiants', summary: 'Lister les étudiants déjà affectés à une promotion', tags: ['Registre étudiants'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'recherche', in: 'query', schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'niveau_id', in: 'query', schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'promotion_id', in: 'query', schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'annee_entree', in: 'query', schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'annee_academique_id', in: 'query', schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'statut', in: 'query', schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'dossier_statut', in: 'query', schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'par_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 15))], responses: [new OA\Response(response: 200, description: 'Statistiques et liste paginée du registre'), new OA\Response(response: 403, description: 'Réservé à ADMIN et SECRETARIAT'), new OA\Response(response: 422, description: 'Filtres invalides')])]
    public function index(Request $request): JsonResponse
    {
        $filtres = $request->validate([
            'recherche' => ['sometimes', 'string', 'max:150'],
            'niveau_id' => ['sometimes', 'integer', Rule::exists('niveaux', 'id')->whereNull('deleted_at')],
            'promotion_id' => ['sometimes', 'integer', Rule::exists('promotions', 'id')->whereNull('deleted_at')],
            'annee_entree' => ['sometimes', 'integer', 'min:1900', 'max:9999'],
            'annee_academique_id' => ['sometimes', 'integer', Rule::exists('annees_academiques', 'id')->whereNull('deleted_at')],
            'statut' => ['sometimes', 'string', 'max:50'],
            'dossier_statut' => ['sometimes', 'string', 'max:30'],
            'par_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $registreComplet = Etudiant::query()->whereHas('inscriptionActuelle');
        $requete = $this->appliquerFiltres(clone $registreComplet, $filtres)
            ->with([
                'eglise:id,code,nom',
                'dossier.fichiers',
                'inscriptionActuelle.promotion.niveau',
                'inscriptionActuelle.anneeAcademique',
            ]);

        $page = $requete
            ->orderBy('nom')
            ->orderBy('prenoms')
            ->paginate($filtres['par_page'] ?? 15)
            ->withQueryString();
        $page->through(fn (Etudiant $etudiant) => $this->formater($etudiant));

        return response()->json([
            'statistiques' => [
                'total' => (clone $registreComplet)->count(),
                'en_formation' => $this->compterStatut(clone $registreComplet, ['En formation']),
                'diplomes' => $this->compterStatut(clone $registreComplet, ['Diplômé', 'Diplome']),
                'departs' => $this->compterStatut(clone $registreComplet, ['Départ de la formation', 'Abandon']),
                'archives' => $this->compterStatut(clone $registreComplet, ['Archivé', 'Archive']),
            ],
            'registre' => $page,
        ]);
    }

    #[OA\Patch(path: '/administration/registre-etudiants/{id}', operationId: 'modifierEtudiantAffecte', summary: 'Modifier l’affectation, le statut ou le dossier d’un étudiant', description: 'Le niveau ne peut pas être envoyé : il est automatiquement déterminé par la promotion.', tags: ['Registre étudiants'], security: [['sanctum' => []]], parameters: [new OA\PathParameter(name: 'id', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'id_promotion', type: 'integer'), new OA\Property(property: 'statut', type: 'string'), new OA\Property(property: 'decision_passage', type: 'string', nullable: true), new OA\Property(property: 'date_decision', type: 'string', format: 'date-time', nullable: true), new OA\Property(property: 'dossier_statut', type: 'string'), new OA\Property(property: 'observations', type: 'string', nullable: true)])), responses: [new OA\Response(response: 200, description: 'Affectation mise à jour'), new OA\Response(response: 403, description: 'Réservé à ADMIN et SECRETARIAT'), new OA\Response(response: 404, description: 'Étudiant ou inscription introuvable'), new OA\Response(response: 422, description: 'Données invalides')])]
    public function update(Request $request, int $id): JsonResponse
    {
        $donnees = $request->validate([
            'id_promotion' => ['sometimes', 'integer', Rule::exists('promotions', 'id')->whereNull('deleted_at')],
            'statut' => ['sometimes', 'string', 'max:50'],
            'decision_passage' => ['sometimes', 'nullable', 'string', 'max:50'],
            'date_decision' => ['sometimes', 'nullable', 'date'],
            'dossier_statut' => ['sometimes', 'string', 'max:30'],
            'observations' => ['sometimes', 'nullable', 'string'],
        ]);

        $etudiant = Etudiant::query()->with('inscriptionActuelle')->findOrFail($id);
        $inscription = $etudiant->inscriptionActuelle;
        if (! $inscription) {
            return response()->json(['message' => 'Cet étudiant n’est affecté à aucune promotion.'], 422);
        }

        $promotion = isset($donnees['id_promotion'])
            ? Promotion::query()->with('niveau')->findOrFail($donnees['id_promotion'])
            : null;
        if ($promotion && ! in_array($promotion->statut, ['Active', 'Actif'], true)) {
            return response()->json(['message' => 'La promotion sélectionnée n’est pas active.'], 422);
        }
        if ($promotion && Inscription::query()
            ->where('id_etudiant', $etudiant->id)
            ->where('id_promotion', $promotion->id)
            ->whereKeyNot($inscription->id)
            ->exists()) {
            return response()->json(['message' => 'Cet étudiant possède déjà une inscription dans cette promotion.'], 422);
        }

        DB::transaction(function () use ($request, $donnees, $etudiant, $inscription, $promotion): void {
            $inscription->update(array_filter([
                'id_promotion' => $promotion?->id,
                'statut' => $donnees['statut'] ?? null,
                'decision_passage' => array_key_exists('decision_passage', $donnees) ? $donnees['decision_passage'] : null,
                'date_decision' => array_key_exists('date_decision', $donnees) ? $donnees['date_decision'] : null,
                'observations' => array_key_exists('observations', $donnees) ? $donnees['observations'] : null,
            ], fn ($valeur, $cle) => array_key_exists($cle, $donnees) || ($cle === 'id_promotion' && $promotion), ARRAY_FILTER_USE_BOTH));

            if (isset($donnees['statut'])) {
                $etudiant->update(['statut' => $donnees['statut'], 'updated_by' => $request->user()->id]);
            }
            if (isset($donnees['dossier_statut']) && $etudiant->dossier) {
                $etudiant->dossier->update([
                    'statut' => $donnees['dossier_statut'],
                    'observations' => $donnees['observations'] ?? $etudiant->dossier->observations,
                    'updated_by' => $request->user()->id,
                ]);
            }
        });

        return response()->json([
            'message' => 'Étudiant affecté mis à jour avec succès.',
            'etudiant' => $this->formater($etudiant->fresh()->load([
                'eglise:id,code,nom', 'dossier.fichiers',
                'inscriptionActuelle.promotion.niveau', 'inscriptionActuelle.anneeAcademique',
            ])),
        ]);
    }

    private function appliquerFiltres(Builder $requete, array $filtres): Builder
    {
        return $requete
            ->when($filtres['recherche'] ?? null, fn (Builder $query, string $recherche) => $query->where(fn (Builder $q) => $q
                ->where('matricule', 'like', "%{$recherche}%")
                ->orWhere('nom', 'like', "%{$recherche}%")
                ->orWhere('prenoms', 'like', "%{$recherche}%")
                ->orWhere('email', 'like', "%{$recherche}%")))
            ->when($filtres['dossier_statut'] ?? null, fn (Builder $query, string $statut) => $query->whereHas('dossier', fn (Builder $q) => $q->where('statut', $statut)))
            ->when($filtres['statut'] ?? null, fn (Builder $query, string $statut) => $query->whereHas('inscriptionActuelle', fn (Builder $q) => $q->where('statut', $statut)))
            ->when($filtres['promotion_id'] ?? null, fn (Builder $query, int $id) => $query->whereHas('inscriptionActuelle', fn (Builder $q) => $q->where('id_promotion', $id)))
            ->when($filtres['annee_academique_id'] ?? null, fn (Builder $query, int $id) => $query->whereHas('inscriptionActuelle', fn (Builder $q) => $q->where('id_annee_academique', $id)))
            ->when($filtres['niveau_id'] ?? null, fn (Builder $query, int $id) => $query->whereHas('inscriptionActuelle.promotion', fn (Builder $q) => $q->where('id_niveau', $id)))
            ->when($filtres['annee_entree'] ?? null, fn (Builder $query, int $annee) => $query->whereHas('inscriptionActuelle.promotion', fn (Builder $q) => $q->where('annee_entree', $annee)));
    }

    private function compterStatut(Builder $requete, array $statuts): int
    {
        return $requete->whereHas('inscriptionActuelle', fn (Builder $query) => $query->whereIn('statut', $statuts))->count();
    }

    private function formater(Etudiant $etudiant): array
    {
        $inscription = $etudiant->inscriptionActuelle;
        $piecesRequises = collect($etudiant->dossier?->pieces_requises ?? []);
        $piecesValides = collect($etudiant->dossier?->fichiers ?? [])->filter(fn ($fichier) => $fichier->statut_validation === 'Validé'
            && (! $fichier->date_expiration || $fichier->date_expiration->isFuture()));
        $typesValides = $piecesValides->pluck('type_piece')->map(fn ($type) => mb_strtolower(trim((string) $type)));
        $piecesManquantes = $piecesRequises
            ->reject(fn ($piece) => $typesValides->contains(mb_strtolower(trim((string) $piece))))
            ->values();

        return [
            'id' => $etudiant->id,
            'matricule' => $etudiant->matricule,
            'nom' => $etudiant->nom,
            'prenoms' => $etudiant->prenoms,
            'nom_complet' => trim("{$etudiant->nom} {$etudiant->prenoms}"),
            'email' => $etudiant->email,
            'telephone' => $etudiant->telephone,
            'eglise' => $etudiant->eglise,
            'promotion' => $inscription?->promotion,
            'niveau' => $inscription?->promotion?->niveau,
            'annee_entree' => $inscription?->promotion?->annee_entree,
            'annee_academique' => $inscription?->anneeAcademique,
            'statut' => $inscription?->statut,
            'decision_passage' => $inscription?->decision_passage,
            'dossier' => [
                'id' => $etudiant->dossier?->id,
                'numero' => $etudiant->dossier?->numero_dossier,
                'statut' => $etudiant->dossier?->statut,
                'pieces_a_jour' => $piecesRequises->isNotEmpty() && $piecesManquantes->isEmpty(),
                'nombre_pieces_manquantes' => $piecesManquantes->count(),
                'pieces_manquantes' => $piecesManquantes,
            ],
        ];
    }
}
