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
            new OA\Parameter(name: 'recherche', in: 'query', description: 'Nom, sigle, code ou ville', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'statut', in: 'query', schema: new OA\Schema(type: 'string', enum: ['Active', 'Suspendue', 'Archivée'])),
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
                ]),
            ),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErreurAuthentification')),
        ],
    )]
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
            $donnees['user_code'] = $this->codeDuCompte($donnees['user_id'] ?? null);
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
            new OA\Response(response: 200, description: 'Église trouvée', content: new OA\JsonContent(type: 'object', properties: [
                new OA\Property(property: 'eglise', ref: '#/components/schemas/Eglise'),
            ])),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErreurAuthentification')),
            new OA\Response(response: 404, description: 'Église introuvable'),
        ],
    )]
    public function show(int $id): JsonResponse
    {
        $eglise = Eglise::query()->findOrFail($id);

        return response()->json(['eglise' => $eglise->load('compte')]);
    }

    #[OA\Patch(
        path: '/eglises/{id}',
        operationId: 'modifierEglise',
        summary: 'Modifier partiellement une église',
        security: [['sanctum' => []]],
        tags: ['Églises'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID numérique de l’église', schema: new OA\Schema(type: 'integer', minimum: 1, example: 1))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/EglisePayload')),
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
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/EglisePayload')),
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

        if (array_key_exists('user_id', $donnees)) {
            $donnees['user_code'] = $this->codeDuCompte($donnees['user_id']);
        }

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
