<?php

namespace App\Http\Controllers\Api\V1\Parametre;

use App\DTOs\Api\V1\Parametre\CoursDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Parametre\CreerCoursRequest;
use App\Http\Requests\Api\V1\Parametre\ModifierCoursRequest;
use App\Models\Cours;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CoursController extends Controller
{
    #[OA\Get(path: '/parametres/cours', operationId: 'listerCours', tags: ['Cours'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'module', in: 'query', schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'matiere', in: 'query', schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'niveau', in: 'query', schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'actif', in: 'query', schema: new OA\Schema(type: 'boolean')), new OA\Parameter(name: 'ordre', in: 'query', schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'code', in: 'query', schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'q', in: 'query', schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 200, description: 'Liste filtrée des cours')])]
    public function index(Request $request): JsonResponse
    {
        $cours = Cours::query()
            ->with($this->relations())
            ->whereHas('module', fn ($query) => $query->whereNotNull('id_matiere'))
            ->when($request->filled('module'), fn ($query) => $query->where('id_module', $request->integer('module')))
            ->when($request->filled('id_module'), fn ($query) => $query->where('id_module', $request->integer('id_module')))
            ->when($request->filled('matiere'), fn ($query) => $query->whereHas('module', fn ($q) => $q->where('id_matiere', $request->integer('matiere'))))
            ->when($request->filled('niveau'), fn ($query) => $query->whereHas('module.matiere', fn ($q) => $q->where('id_niveau', $request->integer('niveau'))))
            ->when($request->has('actif'), fn ($query) => $query->where('actif', $request->boolean('actif')))
            ->when($request->filled('ordre'), fn ($query) => $query->where('ordre', $request->integer('ordre')))
            ->when($request->filled('code'), fn ($query) => $query->where('code', $request->input('code')))
            ->when($request->filled('recherche') || $request->filled('q'), function ($query) use ($request) {
                $terme = $request->input('recherche', $request->input('q'));
                $query->where(fn ($q) => $q->where('code', 'like', "%{$terme}%")->orWhere('libelle', 'like', "%{$terme}%"));
            })
            ->orderBy('id_module')->orderBy('ordre')->orderBy('libelle')->get();

        return response()->json(['cours' => $cours]);
    }

    #[OA\Post(path: '/parametres/cours', operationId: 'creerCours', tags: ['Cours'], security: [['sanctum' => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CoursPayload')), responses: [new OA\Response(response: 201, description: 'Cours créé'), new OA\Response(response: 422, description: 'Erreur de validation')])]
    public function store(CreerCoursRequest $request): JsonResponse
    {
        $utilisateur = $request->user();
        $dto = CoursDTO::fromArray($request->validated());
        $cours = Cours::query()->create([
            ...$dto->toArray(), 'user_id' => $utilisateur->id, 'created_by' => $utilisateur->id,
        ]);

        return response()->json(['message' => 'Cours créé avec succès.', 'cours' => $cours->load($this->relations())], 201);
    }

    #[OA\Get(path: '/parametres/cours/{id}', operationId: 'afficherCours', tags: ['Cours'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Cours trouvé')])]
    public function show(int $id): JsonResponse
    {
        return response()->json(['cours' => Cours::query()->with($this->relations())->findOrFail($id)]);
    }

    #[OA\Put(path: '/parametres/cours/{id}', operationId: 'remplacerCours', tags: ['Cours'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CoursPayload')), responses: [new OA\Response(response: 200, description: 'Cours modifié')])]
    #[OA\Patch(path: '/parametres/cours/{id}', operationId: 'modifierCours', tags: ['Cours'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CoursPayload')), responses: [new OA\Response(response: 200, description: 'Cours modifié')])]
    public function update(ModifierCoursRequest $request, int $id): JsonResponse
    {
        $cours = Cours::query()->findOrFail($id);
        $dto = CoursDTO::fromArray($request->validated());
        $cours->update([...$dto->toArray(), 'updated_by' => $request->user()->id]);

        return response()->json(['message' => 'Cours modifié avec succès.', 'cours' => $cours->fresh()->load($this->relations())]);
    }

    #[OA\Delete(path: '/parametres/cours/{id}', operationId: 'supprimerCours', tags: ['Cours'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Cours supprimé')])]
    public function destroy(ModifierCoursRequest $request, int $id): JsonResponse
    {
        $cours = Cours::query()->findOrFail($id);
        $cours->update(['deleted_by' => $request->user()->id]);
        $cours->delete();

        return response()->json(['message' => 'Cours supprimé avec succès.']);
    }

    /** @return array<int, string> */
    private function relations(): array
    {
        return ['module:id,id_matiere,code,libelle,ordre', 'module.matiere:id,code,libelle,id_niveau,active', 'module.matiere.niveau:id,code,libelle,rang,statut'];
    }
}
