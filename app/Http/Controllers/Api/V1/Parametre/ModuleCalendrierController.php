<?php

namespace App\Http\Controllers\Api\V1\Parametre;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Parametre\CreerModuleCalendrierRequest;
use App\Http\Requests\Api\V1\Parametre\ModifierModuleCalendrierRequest;
use App\Models\EvenementCalendrier;
use App\Models\ModuleCalendrier;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class ModuleCalendrierController extends Controller
{
    #[OA\Get(
        path: '/parametres/modules-calendrier',
        operationId: 'listerModulesCalendrier',
        summary: 'Lister les modules du calendrier par ordre',
        security: [['sanctum' => []]],
        tags: ['Modules Calendrier'],
        parameters: [
            new OA\Parameter(name: 'id_calendrier', in: 'query', required: false, description: 'Filtrer par calendrier', schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des modules', content: new OA\JsonContent(type: 'object', properties: [
                new OA\Property(property: 'modules', type: 'array', items: new OA\Items(ref: '#/components/schemas/ModuleCalendrier')),
            ])),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErreurAuthentification')),
        ],
    )]
    public function index(): JsonResponse
    {
        $modules = ModuleCalendrier::query()
            ->with(['calendrier', 'evenements'])
            ->when(
                request()->filled('id_calendrier'),
                fn ($q) => $q->where('id_calendrier', request()->integer('id_calendrier'))
            )
            ->orderBy('ordre')
            ->get();

        return response()->json(['modules' => $modules]);
    }

    #[OA\Post(
        path: '/parametres/modules-calendrier',
        operationId: 'creerModuleCalendrier',
        summary: 'Créer un module du calendrier',
        description: 'created_by provient automatiquement de l’utilisateur connecté.',
        security: [['sanctum' => []]],
        tags: ['Modules Calendrier'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ModuleCalendrierPayload')),
        responses: [
            new OA\Response(response: 201, description: 'Module créé', content: new OA\JsonContent(type: 'object', properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Module créé avec succès.'),
                new OA\Property(property: 'module', ref: '#/components/schemas/ModuleCalendrier'),
            ])),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErreurAuthentification')),
            new OA\Response(response: 422, description: 'Erreur de validation', content: new OA\JsonContent(ref: '#/components/schemas/ErreurValidation')),
        ],
    )]
    public function store(CreerModuleCalendrierRequest $request): JsonResponse
    {
        $utilisateur = $request->user();
        $donnees = $request->validated();

        $module = new ModuleCalendrier($donnees);
        // id_calendrier n'est pas dans $fillable → assignation manuelle
        $module->id_calendrier = $donnees['id_calendrier'];
        $module->created_by = $utilisateur->id;
        $module->save();

        return response()->json([
            'message' => 'Module créé avec succès.',
            'module'  => $module->load('calendrier'),
        ], 201);
    }

    #[OA\Get(
        path: '/parametres/modules-calendrier/{id}',
        operationId: 'afficherModuleCalendrier',
        summary: 'Afficher un module du calendrier',
        security: [['sanctum' => []]],
        tags: ['Modules Calendrier'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID numérique du module', schema: new OA\Schema(type: 'integer', minimum: 1, example: 1))],
        responses: [
            new OA\Response(response: 200, description: 'Module trouvé', content: new OA\JsonContent(type: 'object', properties: [
                new OA\Property(property: 'module', ref: '#/components/schemas/ModuleCalendrier'),
            ])),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErreurAuthentification')),
            new OA\Response(response: 404, description: 'Module introuvable'),
        ],
    )]
    public function show(int $id): JsonResponse
    {
        $module = ModuleCalendrier::query()
            ->with(['calendrier', 'evenements'])
            ->findOrFail($id);

        return response()->json(['module' => $module]);
    }

    #[OA\Patch(
        path: '/parametres/modules-calendrier/{id}',
        operationId: 'modifierModuleCalendrier',
        summary: 'Modifier partiellement un module du calendrier',
        security: [['sanctum' => []]],
        tags: ['Modules Calendrier'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID numérique du module', schema: new OA\Schema(type: 'integer', minimum: 1, example: 1))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ModuleCalendrierPayload')),
        responses: [
            new OA\Response(response: 200, description: 'Module modifié', content: new OA\JsonContent(type: 'object', properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Module modifié avec succès.'),
                new OA\Property(property: 'module', ref: '#/components/schemas/ModuleCalendrier'),
            ])),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErreurAuthentification')),
            new OA\Response(response: 404, description: 'Module introuvable'),
            new OA\Response(response: 422, description: 'Erreur de validation', content: new OA\JsonContent(ref: '#/components/schemas/ErreurValidation')),
        ],
    )]
    #[OA\Put(
        path: '/parametres/modules-calendrier/{id}',
        operationId: 'remplacerModuleCalendrier',
        summary: 'Modifier un module du calendrier',
        security: [['sanctum' => []]],
        tags: ['Modules Calendrier'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID numérique du module', schema: new OA\Schema(type: 'integer', minimum: 1, example: 1))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ModuleCalendrierPayload')),
        responses: [
            new OA\Response(response: 200, description: 'Module modifié', content: new OA\JsonContent(type: 'object', properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Module modifié avec succès.'),
                new OA\Property(property: 'module', ref: '#/components/schemas/ModuleCalendrier'),
            ])),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErreurAuthentification')),
            new OA\Response(response: 404, description: 'Module introuvable'),
            new OA\Response(response: 422, description: 'Erreur de validation', content: new OA\JsonContent(ref: '#/components/schemas/ErreurValidation')),
        ],
    )]
    public function update(ModifierModuleCalendrierRequest $request, int $id): JsonResponse
    {
        $module = ModuleCalendrier::query()->findOrFail($id);
        $donnees = $request->validated();

        $module->fill($donnees);

        // FK non fillable → assignation manuelle si présente
        if (array_key_exists('id_calendrier', $donnees)) {
            $module->id_calendrier = $donnees['id_calendrier'];
        }

        $module->updated_by = $request->user()->id;
        $module->save();

        return response()->json([
            'message' => 'Module modifié avec succès.',
            'module'  => $module->fresh()->load('calendrier'),
        ]);
    }

    #[OA\Delete(
        path: '/parametres/modules-calendrier/{id}',
        operationId: 'supprimerModuleCalendrier',
        summary: 'Supprimer logiquement un module du calendrier',
        description: 'La suppression est refusée si des événements utilisent le module.',
        security: [['sanctum' => []]],
        tags: ['Modules Calendrier'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID numérique du module', schema: new OA\Schema(type: 'integer', minimum: 1, example: 1))],
        responses: [
            new OA\Response(response: 200, description: 'Module supprimé', content: new OA\JsonContent(type: 'object', properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Module supprimé avec succès.'),
            ])),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErreurAuthentification')),
            new OA\Response(response: 404, description: 'Module introuvable'),
            new OA\Response(response: 422, description: 'Module utilisé par des événements', content: new OA\JsonContent(type: 'object', properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Ce module est utilisé par des événements et ne peut pas être supprimé.'),
            ])),
        ],
    )]
    public function destroy(ModifierModuleCalendrierRequest $request, int $id): JsonResponse
    {
        $module = ModuleCalendrier::query()->findOrFail($id);

        if (EvenementCalendrier::query()->where('id_module_calendrier', $module->id)->exists()) {
            return response()->json([
                'message' => 'Ce module est utilisé par des événements et ne peut pas être supprimé.',
            ], 422);
        }

        $module->update(['deleted_by' => $request->user()->id]);
        $module->delete();

        return response()->json(['message' => 'Module supprimé avec succès.']);
    }
}