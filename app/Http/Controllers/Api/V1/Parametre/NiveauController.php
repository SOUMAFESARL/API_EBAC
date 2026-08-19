<?php

namespace App\Http\Controllers\Api\V1\Parametre;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Parametre\CreerNiveauRequest;
use App\Http\Requests\Api\V1\Parametre\ModifierNiveauRequest;
use App\Models\Niveau;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class NiveauController extends Controller
{
    #[OA\Get(
        path: '/parametres/niveaux',
        operationId: 'listerNiveaux',
        summary: 'Lister les niveaux par rang',
        security: [['sanctum' => []]],
        tags: ['Niveaux'],
        responses: [
            new OA\Response(response: 200, description: 'Liste des niveaux', content: new OA\JsonContent(type: 'object', properties: [
                new OA\Property(property: 'niveaux', type: 'array', items: new OA\Items(ref: '#/components/schemas/Niveau')),
            ])),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErreurAuthentification')),
        ],
    )]
    public function index(): JsonResponse
    {
        return response()->json([
            'niveaux' => Niveau::query()->orderBy('rang')->get(),
        ]);
    }

    #[OA\Post(
        path: '/parametres/niveaux',
        operationId: 'creerNiveau',
        summary: 'Créer un niveau',
        description: 'user_id, user_code et created_by proviennent automatiquement de l’utilisateur connecté.',
        security: [['sanctum' => []]],
        tags: ['Niveaux'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/NiveauPayload')),
        responses: [
            new OA\Response(response: 201, description: 'Niveau créé', content: new OA\JsonContent(type: 'object', properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Niveau créé avec succès.'),
                new OA\Property(property: 'niveau', ref: '#/components/schemas/Niveau'),
            ])),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErreurAuthentification')),
            new OA\Response(response: 422, description: 'Code ou rang invalide/déjà utilisé', content: new OA\JsonContent(ref: '#/components/schemas/ErreurValidation')),
        ],
    )]
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

    #[OA\Get(
        path: '/parametres/niveaux/{id}',
        operationId: 'afficherNiveau',
        summary: 'Afficher un niveau',
        security: [['sanctum' => []]],
        tags: ['Niveaux'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID numérique du niveau', schema: new OA\Schema(type: 'integer', minimum: 1, example: 1))],
        responses: [
            new OA\Response(response: 200, description: 'Niveau trouvé', content: new OA\JsonContent(type: 'object', properties: [
                new OA\Property(property: 'niveau', ref: '#/components/schemas/Niveau'),
            ])),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErreurAuthentification')),
            new OA\Response(response: 404, description: 'Niveau introuvable'),
        ],
    )]
    public function show(int $id): JsonResponse
    {
        $niveau = Niveau::query()->findOrFail($id);

        return response()->json(['niveau' => $niveau]);
    }

    #[OA\Patch(
        path: '/parametres/niveaux/{id}',
        operationId: 'modifierNiveau',
        summary: 'Modifier partiellement un niveau',
        security: [['sanctum' => []]],
        tags: ['Niveaux'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID numérique du niveau', schema: new OA\Schema(type: 'integer', minimum: 1, example: 1))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/NiveauPayload')),
        responses: [
            new OA\Response(response: 200, description: 'Niveau modifié', content: new OA\JsonContent(type: 'object', properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Niveau modifié avec succès.'),
                new OA\Property(property: 'niveau', ref: '#/components/schemas/Niveau'),
            ])),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErreurAuthentification')),
            new OA\Response(response: 404, description: 'Niveau introuvable'),
            new OA\Response(response: 422, description: 'Erreur de validation', content: new OA\JsonContent(ref: '#/components/schemas/ErreurValidation')),
        ],
    )]
    #[OA\Put(
        path: '/parametres/niveaux/{id}',
        operationId: 'remplacerNiveau',
        summary: 'Modifier un niveau',
        security: [['sanctum' => []]],
        tags: ['Niveaux'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID numérique du niveau', schema: new OA\Schema(type: 'integer', minimum: 1, example: 1))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/NiveauPayload')),
        responses: [
            new OA\Response(response: 200, description: 'Niveau modifié', content: new OA\JsonContent(type: 'object', properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Niveau modifié avec succès.'),
                new OA\Property(property: 'niveau', ref: '#/components/schemas/Niveau'),
            ])),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErreurAuthentification')),
            new OA\Response(response: 404, description: 'Niveau introuvable'),
            new OA\Response(response: 422, description: 'Erreur de validation', content: new OA\JsonContent(ref: '#/components/schemas/ErreurValidation')),
        ],
    )]
    public function update(ModifierNiveauRequest $request, int $id): JsonResponse
    {
        $niveau = Niveau::query()->findOrFail($id);
        $niveau->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Niveau modifié avec succès.',
            'niveau' => $niveau->fresh(),
        ]);
    }

    #[OA\Delete(
        path: '/parametres/niveaux/{id}',
        operationId: 'supprimerNiveau',
        summary: 'Supprimer logiquement un niveau',
        description: 'La suppression est refusée si une promotion utilise le niveau.',
        security: [['sanctum' => []]],
        tags: ['Niveaux'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID numérique du niveau', schema: new OA\Schema(type: 'integer', minimum: 1, example: 1))],
        responses: [
            new OA\Response(response: 200, description: 'Niveau supprimé', content: new OA\JsonContent(type: 'object', properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Niveau supprimé avec succès.'),
            ])),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErreurAuthentification')),
            new OA\Response(response: 404, description: 'Niveau introuvable'),
            new OA\Response(response: 422, description: 'Niveau utilisé par une promotion', content: new OA\JsonContent(type: 'object', properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Ce niveau est utilisé par une promotion et ne peut pas être supprimé.'),
            ])),
        ],
    )]
    public function destroy(ModifierNiveauRequest $request, int $id): JsonResponse
    {
        $niveau = Niveau::query()->findOrFail($id);
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
