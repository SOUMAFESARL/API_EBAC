<?php

namespace App\Http\Controllers\Api\V1\Parametre;

use App\DTOs\Api\V1\Parametre\PromotionDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Parametre\CreerPromotionRequest;
use App\Http\Requests\Api\V1\Parametre\ModifierPromotionRequest;
use App\Models\Promotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PromotionController extends Controller
{
    #[OA\Get(path: '/parametres/promotions', operationId: 'listerPromotions', tags: ['Promotions'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'niveau', in: 'query', schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'annee', in: 'query', schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'statut', in: 'query', schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'promotion', in: 'query', schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 200, description: 'Liste filtrée des promotions')])]
    public function index(Request $request): JsonResponse
    {
        $promotions = Promotion::query()
            ->with(['niveau:id,code,libelle', 'anneeAcademique:id,libelle,date_debut,date_fin,active'])
            ->withCount(['inscriptions as nombre_etudiants'])
            ->when($request->filled('niveau'), fn ($query) => $query->where('id_niveau', $request->integer('niveau')))
            ->when($request->filled('id_niveau'), fn ($query) => $query->where('id_niveau', $request->integer('id_niveau')))
            ->when($request->filled('annee'), fn ($query) => $query->where('id_annee_academique', $request->integer('annee')))
            ->when($request->filled('id_annee_academique'), fn ($query) => $query->where('id_annee_academique', $request->integer('id_annee_academique')))
            ->when($request->filled('statut') || $request->filled('status'), fn ($query) => $query->where('statut', $request->input('statut', $request->input('status'))))
            ->when($request->filled('promotion'), function ($query) use ($request) {
                $promotion = $request->string('promotion')->toString();
                $query->where(fn ($sousRequete) => $sousRequete
                    ->when(ctype_digit($promotion), fn ($q) => $q->orWhere('id', (int) $promotion))
                    ->orWhere('code', 'like', "%{$promotion}%"));
            })
            ->orderByDesc('date_ouverture')
            ->orderBy('code')
            ->get();

        return response()->json(['promotions' => $promotions]);
    }

    #[OA\Post(path: '/parametres/promotions', operationId: 'creerPromotion', tags: ['Promotions'], security: [['sanctum' => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/PromotionPayload')), responses: [new OA\Response(response: 201, description: 'Promotion créée'), new OA\Response(response: 422, description: 'Erreur de validation')])]
    public function store(CreerPromotionRequest $request): JsonResponse
    {
        $utilisateur = $request->user();
        $dto = PromotionDTO::fromArray($request->validated());
        $promotion = Promotion::query()->create([
            ...$dto->toArray(),
            'user_id' => $utilisateur->id,
            'created_by' => $utilisateur->id,
        ]);

        return response()->json([
            'message' => 'Promotion créée avec succès.',
            'promotion' => $this->charger($promotion),
        ], 201);
    }

    #[OA\Get(path: '/parametres/promotions/{id}', operationId: 'afficherPromotion', tags: ['Promotions'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Promotion trouvée'), new OA\Response(response: 404, description: 'Introuvable')])]
    public function show(int $id): JsonResponse
    {
        return response()->json(['promotion' => $this->charger(Promotion::query()->findOrFail($id))]);
    }

    #[OA\Put(path: '/parametres/promotions/{id}', operationId: 'remplacerPromotion', tags: ['Promotions'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/PromotionPayload')), responses: [new OA\Response(response: 200, description: 'Promotion modifiée')])]
    #[OA\Patch(path: '/parametres/promotions/{id}', operationId: 'modifierPromotion', tags: ['Promotions'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/PromotionPayload')), responses: [new OA\Response(response: 200, description: 'Promotion modifiée')])]
    public function update(ModifierPromotionRequest $request, int $id): JsonResponse
    {
        $promotion = Promotion::query()->findOrFail($id);
        $dto = PromotionDTO::fromArray($request->validated());
        $donnees = $dto->toArray();
        $dateOuverture = $donnees['date_ouverture'] ?? $promotion->date_ouverture?->toDateString();
        $dateCloture = $donnees['date_cloture'] ?? $promotion->date_cloture?->toDateString();

        if ($dateOuverture && $dateCloture && $dateCloture < $dateOuverture) {
            return response()->json([
                'message' => 'La date de clôture doit être postérieure ou égale à la date d’ouverture.',
                'errors' => ['date_cloture' => ['La date de clôture doit être postérieure ou égale à la date d’ouverture.']],
            ], 422);
        }

        $promotion->update([...$donnees, 'updated_by' => $request->user()->id]);

        return response()->json([
            'message' => 'Promotion modifiée avec succès.',
            'promotion' => $this->charger($promotion->fresh()),
        ]);
    }

    #[OA\Delete(path: '/parametres/promotions/{id}', operationId: 'supprimerPromotion', tags: ['Promotions'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Promotion supprimée')])]
    public function destroy(ModifierPromotionRequest $request, int $id): JsonResponse
    {
        $promotion = Promotion::query()->findOrFail($id);
        $promotion->update(['deleted_by' => $request->user()->id]);
        $promotion->delete();

        return response()->json(['message' => 'Promotion supprimée avec succès.']);
    }

    private function charger(Promotion $promotion): Promotion
    {
        return $promotion->load(['niveau:id,code,libelle', 'anneeAcademique:id,libelle,date_debut,date_fin,active'])
            ->loadCount(['inscriptions as nombre_etudiants']);
    }
}
