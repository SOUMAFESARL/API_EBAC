<?php

namespace App\Http\Controllers\Api\V1\Parametre;

use App\DTOs\Api\V1\Parametre\MatiereDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Parametre\CreerMatiereRequest;
use App\Http\Requests\Api\V1\Parametre\ModifierMatiereRequest;
use App\Models\Cours;
use App\Models\Matiere;
use App\Models\Module;
use App\Services\AffectationEnseignantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class MatiereController extends Controller
{
    public function __construct(private AffectationEnseignantService $affectations) {}

    #[OA\Get(path: '/parametres/matieres', operationId: 'listerMatieres', tags: ['Matières'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'niveau', in: 'query', schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'type', in: 'query', schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'active', in: 'query', schema: new OA\Schema(type: 'boolean')), new OA\Parameter(name: 'obligatoire', in: 'query', schema: new OA\Schema(type: 'boolean')), new OA\Parameter(name: 'version', in: 'query', schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'q', in: 'query', schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 200, description: 'Liste filtrée des matières')])]
    public function index(Request $request): JsonResponse
    {
        $matieres = Matiere::query()
            ->with($this->relations())
            ->withCount('modules as nombre_modules')
            ->when($request->filled('niveau'), fn ($query) => $query->where('id_niveau', $request->integer('niveau')))
            ->when($request->filled('id_niveau'), fn ($query) => $query->where('id_niveau', $request->integer('id_niveau')))
            ->when($request->filled('enseignant') || $request->filled('enseignant_id'), fn ($query) => $query->where('enseignant_id', $request->integer('enseignant', $request->integer('enseignant_id'))))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->input('type')))
            ->when($request->has('active'), fn ($query) => $query->where('active', $request->boolean('active')))
            ->when($request->has('obligatoire'), fn ($query) => $query->where('obligatoire', $request->boolean('obligatoire')))
            ->when($request->filled('version'), fn ($query) => $query->where('version', $request->integer('version')))
            ->when($request->filled('module_calendrier_id'), function ($query) use ($request) {
                $ids = array_map('intval', (array) $request->input('module_calendrier_id'));
                $query->whereHas('modulesCalendrier', fn ($module) => $module->whereIn('modules_calendrier.id', $ids));
            })
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
        $donnees = $request->validated();
        $modules = $donnees['modules'] ?? [];
        $modulesCalendrier = $donnees['module_calendrier_id'] ?? [];
        unset($donnees['modules'], $donnees['module_calendrier_id']);

        $matiere = DB::transaction(function () use ($donnees, $modules, $modulesCalendrier, $utilisateur): Matiere {
            $dto = MatiereDTO::fromArray($donnees);
            $matiere = Matiere::query()->create([
                ...$dto->toArray(),
                'user_id' => $utilisateur->id,
                'created_by' => $utilisateur->id,
            ]);
            $matiere->modulesCalendrier()->sync($modulesCalendrier);
            if (isset($donnees['enseignant_id'])) {
                $this->affectations->synchroniserMatiere($matiere, (int) $donnees['enseignant_id'], $utilisateur->id);
            }

            foreach ($modules as $indexModule => $donneesModule) {
                $cours = $donneesModule['cours'];
                unset($donneesModule['cours']);

                $module = Module::query()->create([
                    ...$donneesModule,
                    'id_matiere' => $matiere->id,
                    'ordre' => $donneesModule['ordre'] ?? $indexModule + 1,
                    'user_id' => $utilisateur->id,
                    'created_by' => $utilisateur->id,
                ]);

                foreach ($cours as $indexCours => $donneesCours) {
                    Cours::query()->create([
                        ...$donneesCours,
                        'id_module' => $module->id,
                        'ordre' => $donneesCours['ordre'] ?? $indexCours + 1,
                        'user_id' => $utilisateur->id,
                        'created_by' => $utilisateur->id,
                    ]);
                }
            }

            return $matiere;
        });

        return response()->json([
            'message' => 'Matière créée avec succès.',
            'matiere' => $this->charger($matiere),
        ], 201);
    }

    #[OA\Get(path: '/parametres/matieres/{id}', operationId: 'afficherMatiere', tags: ['Matières'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Matière trouvée')])]
    public function show(int $id): JsonResponse
    {
        return response()->json([
            'matiere' => $this->charger(Matiere::query()->findOrFail($id)),
        ]);
    }

    #[OA\Put(path: '/parametres/matieres/{id}', operationId: 'remplacerMatiere', tags: ['Matières'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/MatierePayload')), responses: [new OA\Response(response: 200, description: 'Matière modifiée')])]
    #[OA\Patch(path: '/parametres/matieres/{id}', operationId: 'modifierMatiere', tags: ['Matières'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/MatierePayload')), responses: [new OA\Response(response: 200, description: 'Matière modifiée')])]
    public function update(ModifierMatiereRequest $request, int $id): JsonResponse
    {
        $matiere = DB::transaction(function () use ($request, $id) {
            $matiere = Matiere::query()->lockForUpdate()->findOrFail($id);
            $donnees = $request->validated();
            $synchroniser = array_key_exists('module_calendrier_id', $donnees);
            $modulesCalendrier = $donnees['module_calendrier_id'] ?? [];
            unset($donnees['module_calendrier_id']);
            $dto = MatiereDTO::fromArray($donnees);
            $matiere->update([...$dto->toArray(), 'updated_by' => $request->user()->id]);
            if ($synchroniser) {
                $matiere->modulesCalendrier()->sync($modulesCalendrier);
            }
            if (array_key_exists('enseignant_id', $donnees)) {
                $this->affectations->synchroniserMatiere($matiere, $donnees['enseignant_id'] === null ? null : (int) $donnees['enseignant_id'], $request->user()->id);
            }

            return $matiere;
        });

        return response()->json([
            'message' => 'Matière modifiée avec succès.',
            'matiere' => $this->charger($matiere->fresh()),
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

    /** @return array<string, mixed> */
    private function relations(): array
    {
        return [
            'niveau:id,code,libelle,rang,statut',
            'enseignant:id,code,nom,prenoms,email,id_role',
            'enseignant.role:id,code,libelle',
            'modulesCalendrier' => fn ($query) => $query->orderBy('ordre')->orderBy('id'),
            'modules' => fn ($query) => $query->orderBy('ordre')->orderBy('id'),
            'modules.cours' => fn ($query) => $query->orderBy('ordre')->orderBy('id'),
        ];
    }

    private function charger(Matiere $matiere): Matiere
    {
        return $matiere->load($this->relations())->loadCount('modules as nombre_modules');
    }
}
