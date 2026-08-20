<?php

namespace App\Http\Controllers\Api\V1\Parametre;

use App\DTOs\Api\V1\Parametre\ModuleDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Parametre\CreerModuleRequest;
use App\Http\Requests\Api\V1\Parametre\ModifierModuleRequest;
use App\Models\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ModuleController extends Controller
{
    #[OA\Get(path: '/parametres/modules', operationId: 'listerModules', tags: ['Modules'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'matiere', in: 'query', schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'niveau', in: 'query', schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'ordre', in: 'query', schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'code', in: 'query', schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'q', in: 'query', schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 200, description: 'Liste filtrée des modules')])]
    public function index(Request $request): JsonResponse
    {
        $modules = Module::query()
            ->with('matiere:id,code,libelle,id_niveau,active')
            ->with('matiere.niveau:id,code,libelle,rang,statut')
            ->withCount('cours as nombre_cours')
            ->whereNotNull('id_matiere')
            ->when($request->filled('matiere'), fn ($query) => $query->where('id_matiere', $request->integer('matiere')))
            ->when($request->filled('id_matiere'), fn ($query) => $query->where('id_matiere', $request->integer('id_matiere')))
            ->when($request->filled('niveau'), fn ($query) => $query->whereHas('matiere', fn ($q) => $q->where('id_niveau', $request->integer('niveau'))))
            ->when($request->filled('ordre'), fn ($query) => $query->where('ordre', $request->integer('ordre')))
            ->when($request->filled('code'), fn ($query) => $query->where('code', $request->input('code')))
            ->when($request->filled('recherche') || $request->filled('q'), function ($query) use ($request) {
                $terme = $request->input('recherche', $request->input('q'));
                $query->where(fn ($q) => $q->where('code', 'like', "%{$terme}%")->orWhere('libelle', 'like', "%{$terme}%"));
            })
            ->orderBy('id_matiere')
            ->orderBy('ordre')
            ->orderBy('libelle')
            ->get();

        return response()->json(['modules' => $modules]);
    }

    #[OA\Post(path: '/parametres/modules', operationId: 'creerModule', tags: ['Modules'], security: [['sanctum' => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ModulePayload')), responses: [new OA\Response(response: 201, description: 'Module créé'), new OA\Response(response: 422, description: 'Erreur de validation')])]
    public function store(CreerModuleRequest $request): JsonResponse
    {
        $utilisateur = $request->user();
        $dto = ModuleDTO::fromArray($request->validated());
        $module = Module::query()->create([
            ...$dto->toArray(), 'user_id' => $utilisateur->id, 'created_by' => $utilisateur->id,
        ]);

        return response()->json(['message' => 'Module créé avec succès.', 'module' => $this->charger($module)], 201);
    }

    #[OA\Get(path: '/parametres/modules/{id}', operationId: 'afficherModule', tags: ['Modules'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Module trouvé')])]
    public function show(int $id): JsonResponse
    {
        return response()->json(['module' => $this->charger(Module::query()->findOrFail($id))]);
    }

    #[OA\Put(path: '/parametres/modules/{id}', operationId: 'remplacerModule', tags: ['Modules'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ModulePayload')), responses: [new OA\Response(response: 200, description: 'Module modifié')])]
    #[OA\Patch(path: '/parametres/modules/{id}', operationId: 'modifierModule', tags: ['Modules'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ModulePayload')), responses: [new OA\Response(response: 200, description: 'Module modifié')])]
    public function update(ModifierModuleRequest $request, int $id): JsonResponse
    {
        $module = Module::query()->findOrFail($id);
        $dto = ModuleDTO::fromArray($request->validated());
        $module->update([...$dto->toArray(), 'updated_by' => $request->user()->id]);

        return response()->json(['message' => 'Module modifié avec succès.', 'module' => $this->charger($module->fresh())]);
    }

    #[OA\Delete(path: '/parametres/modules/{id}', operationId: 'supprimerModule', tags: ['Modules'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Module supprimé')])]
    public function destroy(ModifierModuleRequest $request, int $id): JsonResponse
    {
        $module = Module::query()->findOrFail($id);
        $module->update(['deleted_by' => $request->user()->id]);
        $module->delete();

        return response()->json(['message' => 'Module supprimé avec succès.']);
    }

    private function charger(Module $module): Module
    {
        return $module->load(['matiere:id,code,libelle,id_niveau,active', 'matiere.niveau:id,code,libelle,rang,statut'])
            ->loadCount('cours as nombre_cours');
    }
}
