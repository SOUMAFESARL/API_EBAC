<?php

namespace App\Http\Controllers\Api\V1\Eglise;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Eglise\CreerEgliseRequest;
use App\Http\Requests\Api\V1\Eglise\ModifierEgliseRequest;
use App\Models\Eglise;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class EgliseController extends Controller
{
    #[OA\Get(
        path: '/eglises',
        operationId: 'listerEglises',
        summary: 'Lister les églises',
        security: [['sanctum' => []]],
        tags: ['Églises'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, default: 1)),
            new OA\Parameter(name: 'par_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 15)),
            new OA\Parameter(name: 'recherche', in: 'query', description: 'Recherche partielle par nom, sigle, code ou ville. Les paramètres q et eglise sont des alias.', schema: new OA\Schema(type: 'string'), example: 'grâce'),
            new OA\Parameter(name: 'q', in: 'query', description: 'Alias de recherche.', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eglise', in: 'query', description: 'Alias de recherche conservé pour compatibilité.', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'statut', in: 'query', schema: new OA\Schema(type: 'string', enum: ['Active', 'Suspendue', 'Archivée'])),
            new OA\Parameter(name: 'ville', in: 'query', description: 'Filtre exact sur la ville ou commune.', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'ville_commune', in: 'query', description: 'Alias du filtre ville.', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'region', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'district', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'denomination', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'pasteur', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'capacite_min', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 0)),
            new OA\Parameter(name: 'avec_etudiants', in: 'query', description: 'Si vrai, retourne uniquement les églises ayant au moins un étudiant rattaché.', schema: new OA\Schema(type: 'boolean', default: false)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste paginée',
                content: new OA\JsonContent(type: 'object', properties: [
                    new OA\Property(property: 'current_page', type: 'integer', example: 1),
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Eglise')),
                    new OA\Property(property: 'per_page', type: 'integer', example: 15),
                    new OA\Property(property: 'total', type: 'integer', example: 1),
                    new OA\Property(property: 'last_page', type: 'integer', example: 1),
                    new OA\Property(property: 'from', type: 'integer', nullable: true, example: 1),
                    new OA\Property(property: 'to', type: 'integer', nullable: true, example: 1),
                ]),
            ),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErreurAuthentification')),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        $parPage = min(max($request->integer('par_page', 15), 1), 100);
        $recherche = $request->input('recherche', $request->input('q', $request->input('eglise', '')));

        $eglises = Eglise::query()
            ->with($this->relations())
            ->withCount(['etudiants as nombre_etudiants', 'etudiantsHistoriques as nombre_etudiants_historiques'])
            ->when($recherche, fn ($query) => $query->where(function ($query) use ($recherche) {
                $query->where('nom', 'like', "%{$recherche}%")
                    ->orWhere('sigle', 'like', "%{$recherche}%")
                    ->orWhere('code', 'like', "%{$recherche}%")
                    ->orWhere('ville_commune', 'like', "%{$recherche}%");
            }))
            ->when($request->filled('statut'), fn ($query) => $query->where('statut', $request->string('statut')))
            ->when($request->filled('ville') || $request->filled('ville_commune'), fn ($query) => $query->where('ville_commune', $request->input('ville', $request->input('ville_commune'))))
            ->when($request->filled('region'), fn ($query) => $query->where('region', $request->input('region')))
            ->when($request->filled('district'), fn ($query) => $query->where('district', $request->input('district')))
            ->when($request->filled('denomination'), fn ($query) => $query->where('denomination', $request->input('denomination')))
            ->when($request->filled('pasteur'), fn ($query) => $query->where('pasteur_principal', 'like', '%'.$request->input('pasteur').'%'))
            ->when($request->filled('capacite_min'), fn ($query) => $query->where('capacite_max_stagiaires', '>=', $request->integer('capacite_min')))
            ->when($request->boolean('avec_etudiants'), fn ($query) => $query->where(fn ($q) => $q->whereHas('etudiants')->orWhereHas('etudiantsHistoriques')))
            ->orderBy('nom')
            ->paginate($parPage)
            ->withQueryString();

        $eglises->getCollection()->transform(fn (Eglise $eglise) => $this->finaliserCompteur($eglise));

        return response()->json($eglises);
    }

    #[OA\Post(
        path: '/eglises',
        operationId: 'creerEglise',
        summary: 'Créer une église',
        description: 'Le code EGL-xxxxxx et les champs d’audit sont générés automatiquement.',
        security: [['sanctum' => []]],
        tags: ['Églises'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/EglisePayload')),
        responses: [
            new OA\Response(response: 201, description: 'Église créée', content: new OA\JsonContent(type: 'object', properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Église créée avec succès.'),
                new OA\Property(property: 'eglise', ref: '#/components/schemas/Eglise'),
            ])),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErreurAuthentification')),
            new OA\Response(response: 422, description: 'Erreur de validation', content: new OA\JsonContent(ref: '#/components/schemas/ErreurValidation')),
        ],
    )]
    public function store(CreerEgliseRequest $request): JsonResponse
    {
        $donnees = $request->validated();
        $administrateur = $request->user();

        $eglise = DB::transaction(function () use ($donnees, $administrateur) {
            $donnees['code'] = $this->genererCode();
            $donnees['user_id'] = $administrateur->id;
            $donnees['user_code'] = $administrateur->code;
            $donnees['created_by'] = $administrateur->id;

            return Eglise::query()->create($donnees);
        });

        return response()->json(['message' => 'Église créée avec succès.', 'eglise' => $eglise], 201);
    }

    #[OA\Get(
        path: '/eglises/{id}',
        operationId: 'afficherEglise',
        summary: 'Afficher une église',
        security: [['sanctum' => []]],
        tags: ['Églises'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID numérique de l’église', schema: new OA\Schema(type: 'integer', minimum: 1, example: 1))],
        responses: [
            new OA\Response(response: 200, description: 'Église trouvée avec ses relations et ses statistiques étudiantes', content: new OA\JsonContent(type: 'object', properties: [
                new OA\Property(property: 'eglise', ref: '#/components/schemas/EgliseDetail'),
            ])),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErreurAuthentification')),
            new OA\Response(response: 404, description: 'Église introuvable'),
        ],
    )]
    public function show(int $id): JsonResponse
    {
        $eglise = Eglise::query()
            ->with($this->relations())
            ->withCount(['etudiants as nombre_etudiants', 'etudiantsHistoriques as nombre_etudiants_historiques'])
            ->findOrFail($id);

        $eglise = $this->finaliserCompteur($eglise);
        $eglise->statistiques_etudiants = $this->statistiquesEtudiants($eglise->id);
        $eglise->nombre_etudiants = $eglise->statistiques_etudiants['total'];

        return response()->json(['eglise' => $eglise]);
    }

    #[OA\Patch(
        path: '/eglises/{id}',
        operationId: 'modifierEglise',
        summary: 'Modifier partiellement une église',
        security: [['sanctum' => []]],
        tags: ['Églises'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID numérique de l’église', schema: new OA\Schema(type: 'integer', minimum: 1, example: 1))],
        requestBody: new OA\RequestBody(required: true, description: 'L’API applique les mêmes règles de modification partielle pour PUT et PATCH.', content: new OA\JsonContent(ref: '#/components/schemas/EgliseModificationPayload')),
        responses: [
            new OA\Response(response: 200, description: 'Église modifiée', content: new OA\JsonContent(type: 'object', properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Église modifiée avec succès.'),
                new OA\Property(property: 'eglise', ref: '#/components/schemas/Eglise'),
            ])),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErreurAuthentification')),
            new OA\Response(response: 404, description: 'Église introuvable'),
            new OA\Response(response: 422, description: 'Erreur de validation', content: new OA\JsonContent(ref: '#/components/schemas/ErreurValidation')),
        ],
    )]
    #[OA\Put(
        path: '/eglises/{id}',
        operationId: 'remplacerEglise',
        summary: 'Modifier une église',
        security: [['sanctum' => []]],
        tags: ['Églises'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID numérique de l’église', schema: new OA\Schema(type: 'integer', minimum: 1, example: 1))],
        requestBody: new OA\RequestBody(required: true, description: 'L’API applique les mêmes règles de modification partielle pour PUT et PATCH.', content: new OA\JsonContent(ref: '#/components/schemas/EgliseModificationPayload')),
        responses: [
            new OA\Response(response: 200, description: 'Église modifiée', content: new OA\JsonContent(type: 'object', properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Église modifiée avec succès.'),
                new OA\Property(property: 'eglise', ref: '#/components/schemas/Eglise'),
            ])),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErreurAuthentification')),
            new OA\Response(response: 404, description: 'Église introuvable'),
            new OA\Response(response: 422, description: 'Erreur de validation', content: new OA\JsonContent(ref: '#/components/schemas/ErreurValidation')),
        ],
    )]
    public function update(ModifierEgliseRequest $request, int $id): JsonResponse
    {
        $eglise = Eglise::query()->findOrFail($id);
        $donnees = $request->validated();

        $eglise->update([...$donnees, 'updated_by' => $request->user()->id]);

        return response()->json([
            'message' => 'Église modifiée avec succès.',
            'eglise' => $eglise->fresh(),
        ]);
    }

    #[OA\Delete(
        path: '/eglises/{id}',
        operationId: 'supprimerEglise',
        summary: 'Supprimer logiquement une église',
        security: [['sanctum' => []]],
        tags: ['Églises'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID numérique de l’église', schema: new OA\Schema(type: 'integer', minimum: 1, example: 1))],
        responses: [
            new OA\Response(response: 200, description: 'Église supprimée', content: new OA\JsonContent(type: 'object', properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Église supprimée avec succès.'),
            ])),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErreurAuthentification')),
            new OA\Response(response: 404, description: 'Église introuvable'),
        ],
    )]
    public function destroy(Request $request, int $id): JsonResponse
    {
        $eglise = Eglise::query()->findOrFail($id);
        $eglise->update(['deleted_by' => $request->user()->id]);
        $eglise->delete();

        return response()->json(['message' => 'Église supprimée avec succès.']);
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

    /** @return array<int, string> */
    private function relations(): array
    {
        return [
            'compte:id,code,nom,prenoms,email',
            'createur:id,code,nom,prenoms,email',
            'modificateur:id,code,nom,prenoms,email',
        ];
    }

    private function finaliserCompteur(Eglise $eglise): Eglise
    {
        $eglise->nombre_etudiants = (int) $eglise->nombre_etudiants
            + (int) $eglise->nombre_etudiants_historiques;
        unset($eglise->nombre_etudiants_historiques);

        return $eglise;
    }

    /** @return array{total: int, avec_niveau: int, sans_niveau: int, par_niveau: array<int, array<string, int|string>>} */
    private function statistiquesEtudiants(int $egliseId): array
    {
        $etudiantsEglise = DB::table('etudiants')
            ->select('id')
            ->whereNull('deleted_at')
            ->where(fn ($query) => $query
                ->where('eglise_id', $egliseId)
                ->orWhere('id_eglise', $egliseId));

        $dernieresInscriptions = DB::table('inscriptions')
            ->selectRaw('id_etudiant, MAX(id) as derniere_inscription_id')
            ->groupBy('id_etudiant');

        $parNiveau = DB::query()
            ->fromSub($etudiantsEglise, 'etudiants_eglise')
            ->joinSub($dernieresInscriptions, 'dernieres_inscriptions', fn ($join) => $join
                ->on('dernieres_inscriptions.id_etudiant', '=', 'etudiants_eglise.id'))
            ->join('inscriptions', 'inscriptions.id', '=', 'dernieres_inscriptions.derniere_inscription_id')
            ->join('promotions', fn ($join) => $join
                ->on('promotions.id', '=', 'inscriptions.id_promotion')
                ->whereNull('promotions.deleted_at'))
            ->join('niveaux', fn ($join) => $join
                ->on('niveaux.id', '=', 'promotions.id_niveau')
                ->whereNull('niveaux.deleted_at'))
            ->selectRaw('niveaux.id as niveau_id, niveaux.code as niveau_code, niveaux.libelle as niveau_libelle, niveaux.rang as niveau_rang, COUNT(*) as nombre_etudiants')
            ->groupBy('niveaux.id', 'niveaux.code', 'niveaux.libelle', 'niveaux.rang')
            ->orderBy('niveaux.rang')
            ->get()
            ->map(fn ($niveau) => [
                'niveau_id' => (int) $niveau->niveau_id,
                'niveau_code' => $niveau->niveau_code,
                'niveau_libelle' => $niveau->niveau_libelle,
                'niveau_rang' => (int) $niveau->niveau_rang,
                'nombre_etudiants' => (int) $niveau->nombre_etudiants,
            ])
            ->all();

        $total = DB::query()->fromSub($etudiantsEglise, 'etudiants_eglise')->count();
        $avecNiveau = array_sum(array_column($parNiveau, 'nombre_etudiants'));

        return [
            'total' => $total,
            'avec_niveau' => $avecNiveau,
            'sans_niveau' => $total - $avecNiveau,
            'par_niveau' => $parNiveau,
        ];
    }
}
