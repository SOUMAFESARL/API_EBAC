<?php

namespace App\Http\Controllers\Api\V1\Parametre;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Parametre\CreerCiviliteRequest;
use App\Http\Requests\Api\V1\Parametre\ModifierCiviliteRequest;
use App\Models\Civilite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CiviliteController extends Controller
{
    #[OA\Get(path: '/parametres/civilites', operationId: 'listerCivilites', summary: 'Lister les civilités', tags: ['Civilités'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'actif', in: 'query', schema: new OA\Schema(type: 'boolean')), new OA\Parameter(name: 'recherche', in: 'query', schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 200, description: 'Liste des civilités', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'civilites', type: 'array', items: new OA\Items(ref: '#/components/schemas/Civilite'))])), new OA\Response(response: 401, description: 'Non authentifié')])]
    public function index(Request $request): JsonResponse
    {
        $civilites = Civilite::query()
            ->when($request->has('actif'), fn ($query) => $query->where('actif', $request->boolean('actif')))
            ->when($request->string('recherche')->toString(), function ($query, string $recherche) {
                $query->where(fn ($query) => $query->where('name', 'like', "%{$recherche}%")
                    ->orWhere('code', 'like', "%{$recherche}%")
                    ->orWhere('abreviation', 'like', "%{$recherche}%"));
            })
            ->orderBy('name')
            ->get();

        return response()->json(['civilites' => $civilites]);
    }

    #[OA\Get(path: '/parametres/civilites/create', operationId: 'preparerCreationCivilite', summary: 'Obtenir les valeurs du formulaire de création', tags: ['Civilités'], security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'Valeurs par défaut'), new OA\Response(response: 401, description: 'Non authentifié')])]
    public function create(): JsonResponse
    {
        return response()->json(['valeurs_par_defaut' => ['actif' => true]]);
    }

    #[OA\Post(path: '/parametres/civilites', operationId: 'creerCivilite', summary: 'Créer une civilité', tags: ['Civilités'], security: [['sanctum' => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CivilitePayload')), responses: [new OA\Response(response: 201, description: 'Civilité créée'), new OA\Response(response: 401, description: 'Non authentifié'), new OA\Response(response: 422, description: 'Données invalides')])]
    public function store(CreerCiviliteRequest $request): JsonResponse
    {
        $civilite = Civilite::query()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Civilité créée avec succès.', 'civilite' => $civilite], 201);
    }

    #[OA\Get(path: '/parametres/civilites/{id}', operationId: 'afficherCivilite', summary: 'Afficher une civilité', tags: ['Civilités'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Civilité trouvée'), new OA\Response(response: 401, description: 'Non authentifié'), new OA\Response(response: 404, description: 'Civilité introuvable')])]
    public function show(int $id): JsonResponse
    {
        return response()->json(['civilite' => Civilite::query()->findOrFail($id)]);
    }

    #[OA\Get(path: '/parametres/civilites/{id}/edit', operationId: 'preparerModificationCivilite', summary: 'Obtenir une civilité à modifier', tags: ['Civilités'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Civilité à modifier'), new OA\Response(response: 401, description: 'Non authentifié'), new OA\Response(response: 404, description: 'Civilité introuvable')])]
    public function edit(int $id): JsonResponse
    {
        return response()->json(['civilite' => Civilite::query()->findOrFail($id)]);
    }

    #[OA\Put(path: '/parametres/civilites/{id}', operationId: 'remplacerCivilite', summary: 'Modifier une civilité', tags: ['Civilités'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CivilitePayload')), responses: [new OA\Response(response: 200, description: 'Civilité modifiée'), new OA\Response(response: 401, description: 'Non authentifié'), new OA\Response(response: 404, description: 'Civilité introuvable'), new OA\Response(response: 422, description: 'Données invalides')])]
    #[OA\Patch(path: '/parametres/civilites/{id}', operationId: 'modifierCivilite', summary: 'Modifier partiellement une civilité', tags: ['Civilités'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CivilitePayload')), responses: [new OA\Response(response: 200, description: 'Civilité modifiée'), new OA\Response(response: 401, description: 'Non authentifié'), new OA\Response(response: 404, description: 'Civilité introuvable'), new OA\Response(response: 422, description: 'Données invalides')])]
    public function update(ModifierCiviliteRequest $request, int $id): JsonResponse
    {
        $civilite = Civilite::query()->findOrFail($id);
        $civilite->update([...$request->validated(), 'updated_by' => $request->user()->id]);

        return response()->json(['message' => 'Civilité modifiée avec succès.', 'civilite' => $civilite->fresh()]);
    }

    #[OA\Delete(path: '/parametres/civilites/{id}', operationId: 'supprimerCivilite', summary: 'Supprimer logiquement une civilité', tags: ['Civilités'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Civilité supprimée'), new OA\Response(response: 401, description: 'Non authentifié'), new OA\Response(response: 404, description: 'Civilité introuvable')])]
    public function destroy(Request $request, int $id): JsonResponse
    {
        $civilite = Civilite::query()->findOrFail($id);
        $civilite->update(['deleted_by' => $request->user()->id]);
        $civilite->delete();

        return response()->json(['message' => 'Civilité supprimée avec succès.']);
    }
}
