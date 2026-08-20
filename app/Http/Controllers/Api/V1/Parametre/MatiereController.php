<?php

namespace App\Http\Controllers\Api\V1\Parametre;

use App\DTOs\Api\V1\Parametre\MatiereDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Parametre\CreerMatiereRequest;
use App\Http\Requests\Api\V1\Parametre\ModifierMatiereRequest;
use App\Models\Matiere;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class MatiereController extends Controller
{
    #[OA\Get(path: '/parametres/matieres', operationId: 'listerMatieres', tags: ['Matières'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'niveau', in: 'query', schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'type', in: 'query', schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'active', in: 'query', schema: new OA\Schema(type: 'boolean')), new OA\Parameter(name: 'obligatoire', in: 'query', schema: new OA\Schema(type: 'boolean')), new OA\Parameter(name: 'version', in: 'query', schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'q', in: 'query', schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 200, description: 'Liste filtrée des matières')])]
    public function index(Request $request): JsonResponse
    {
        $matieres = Matiere::query()
            ->with('niveau:id,code,libelle,rang,statut')
            ->when($request->filled('niveau'), fn ($query) => $query->where('id_niveau', $request->integer('niveau')))
            ->when($request->filled('id_niveau'), fn ($query) => $query->where('id_niveau', $request->integer('id_niveau')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->input('type')))
            ->when($request->has('active'), fn ($query) => $query->where('active', $request->boolean('active')))
            ->when($request->has('obligatoire'), fn ($query) => $query->where('obligatoire', $request->boolean('obligatoire')))
            ->when($request->filled('version'), fn ($query) => $query->where('version', $request->integer('version')))
            ->when($request->filled('recherche') || $request->filled('q'), function ($query) use ($request) {
                $terme = $request->input('recherche', $request->input('q'));
                $query->where(fn ($sousRequete) => $sousRequete
                    ->where('code', 'like', "%{$terme}%")
                    ->orWhere('libelle', 'like', "%{$terme}%"));
            })
            ->orderBy('libelle')
            ->get();

        return response()->json(['matieres' => $matieres]);
    }

    #[OA\Post(path: '/parametres/matieres', operationId: 'creerMatiere', tags: ['Matières'], security: [['sanctum' => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/MatierePayload')), responses: [new OA\Response(response: 201, description: 'Matière créée'), new OA\Response(response: 422, description: 'Erreur de validation')])]
    public function store(CreerMatiereRequest $request): JsonResponse
    {
        $utilisateur = $request->user();
        $dto = MatiereDTO::fromArray($request->validated());
        $matiere = Matiere::query()->create([
            ...$dto->toArray(),
            'user_id' => $utilisateur->id,
            'created_by' => $utilisateur->id,
        ]);

        return response()->json([
            'message' => 'Matière créée avec succès.',
            'matiere' => $matiere->load('niveau:id,code,libelle,rang,statut'),
        ], 201);
    }

    #[OA\Get(path: '/parametres/matieres/{id}', operationId: 'afficherMatiere', tags: ['Matières'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Matière trouvée')])]
    public function show(int $id): JsonResponse
    {
        return response()->json([
            'matiere' => Matiere::query()->with('niveau:id,code,libelle,rang,statut')->findOrFail($id),
        ]);
    }

    #[OA\Put(path: '/parametres/matieres/{id}', operationId: 'remplacerMatiere', tags: ['Matières'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/MatierePayload')), responses: [new OA\Response(response: 200, description: 'Matière modifiée')])]
    #[OA\Patch(path: '/parametres/matieres/{id}', operationId: 'modifierMatiere', tags: ['Matières'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/MatierePayload')), responses: [new OA\Response(response: 200, description: 'Matière modifiée')])]
    public function update(ModifierMatiereRequest $request, int $id): JsonResponse
    {
        $matiere = Matiere::query()->findOrFail($id);
        $dto = MatiereDTO::fromArray($request->validated());
        $matiere->update([...$dto->toArray(), 'updated_by' => $request->user()->id]);

        return response()->json([
            'message' => 'Matière modifiée avec succès.',
            'matiere' => $matiere->fresh()->load('niveau:id,code,libelle,rang,statut'),
        ]);
    }

    #[OA\Delete(path: '/parametres/matieres/{id}', operationId: 'supprimerMatiere', tags: ['Matières'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Matière supprimée')])]
    public function destroy(ModifierMatiereRequest $request, int $id): JsonResponse
    {
        $matiere = Matiere::query()->findOrFail($id);
        $matiere->update(['deleted_by' => $request->user()->id]);
        $matiere->delete();

        return response()->json(['message' => 'Matière supprimée avec succès.']);
    }
}
