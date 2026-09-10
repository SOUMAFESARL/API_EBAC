<?php

namespace App\Http\Controllers\Api\V1\Parametre;

use App\DTOs\Api\V1\Parametre\AnneeAcademiqueDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Parametre\CreerAnneeAcademiqueRequest;
use App\Http\Requests\Api\V1\Parametre\ModifierAnneeAcademiqueRequest;
use App\Models\AnneeAcademique;
use App\Services\CalendrierAcademiqueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class AnneeAcademiqueController extends Controller
{
    #[OA\Get(path: '/parametres/annees-academiques', operationId: 'listerAnneesAcademiques', tags: ['Années académiques'], security: [['sanctum' => []]], responses: [new OA\Response(response: 403, description: 'Acc?s r?serv? aux comptes actifs ADMIN, SECRETAIRE_ACADEMIQUE et DIRECTION'), new OA\Response(response: 200, description: 'Liste des années académiques')])]
    public function index(): JsonResponse
    {
        return response()->json([
            'annees_academiques' => AnneeAcademique::query()->orderByDesc('date_debut')->get(),
        ]);
    }

    #[OA\Post(path: '/parametres/annees-academiques', operationId: 'creerAnneeAcademique', tags: ['Années académiques'], security: [['sanctum' => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/AnneeAcademiquePayload')), responses: [new OA\Response(response: 403, description: 'Acc?s r?serv? aux comptes actifs ADMIN, SECRETAIRE_ACADEMIQUE et DIRECTION'), new OA\Response(response: 201, description: 'Année créée'), new OA\Response(response: 422, description: 'Erreur de validation')])]
    public function store(CreerAnneeAcademiqueRequest $request): JsonResponse
    {
        $utilisateur = $request->user();
        $dto = AnneeAcademiqueDTO::fromArray($request->validated());
        $anneeAcademique = DB::transaction(function () use ($dto, $utilisateur) {
            if ($dto->toArray()['active'] ?? false) {
                AnneeAcademique::query()->where('active', true)->update(['active' => false, 'updated_by' => $utilisateur->id]);
            }

            return AnneeAcademique::query()->create([
                ...$dto->toArray(),
                'user_id' => $utilisateur->id,
                'created_by' => $utilisateur->id,
            ]);
        });

        return response()->json([
            'message' => 'Année académique créée avec succès.',
            'annee_academique' => $anneeAcademique,
        ], 201);
    }

    #[OA\Get(path: '/parametres/annees-academiques/{id}', operationId: 'afficherAnneeAcademique', tags: ['Années académiques'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 403, description: 'Acc?s r?serv? aux comptes actifs ADMIN, SECRETAIRE_ACADEMIQUE et DIRECTION'), new OA\Response(response: 200, description: 'Année trouvée'), new OA\Response(response: 404, description: 'Introuvable')])]
    public function show(int $id): JsonResponse
    {
        return response()->json([
            'annee_academique' => AnneeAcademique::query()->findOrFail($id),
        ]);
    }

    #[OA\Put(path: '/parametres/annees-academiques/{id}', operationId: 'remplacerAnneeAcademique', tags: ['Années académiques'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/AnneeAcademiquePayload')), responses: [new OA\Response(response: 403, description: 'Acc?s r?serv? aux comptes actifs ADMIN, SECRETAIRE_ACADEMIQUE et DIRECTION'), new OA\Response(response: 200, description: 'Année modifiée'), new OA\Response(response: 422, description: 'Erreur de validation')])]
    #[OA\Patch(path: '/parametres/annees-academiques/{id}', operationId: 'modifierAnneeAcademique', tags: ['Années académiques'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/AnneeAcademiquePayload')), responses: [new OA\Response(response: 403, description: 'Acc?s r?serv? aux comptes actifs ADMIN, SECRETAIRE_ACADEMIQUE et DIRECTION'), new OA\Response(response: 200, description: 'Année modifiée'), new OA\Response(response: 422, description: 'Erreur de validation')])]
    public function update(ModifierAnneeAcademiqueRequest $request, int $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id) {
            $anneeAcademique = AnneeAcademique::query()->lockForUpdate()->findOrFail($id);
            $dto = AnneeAcademiqueDTO::fromArray($request->validated());
            $donnees = $dto->toArray();
            $dateDebut = $donnees['date_debut'] ?? $anneeAcademique->date_debut->toDateString();
            $dateFin = $donnees['date_fin'] ?? $anneeAcademique->date_fin->toDateString();

            if ($dateFin <= $dateDebut) {
                return response()->json([
                    'message' => 'La date de fin doit être postérieure à la date de début.',
                    'errors' => ['date_fin' => ['La date de fin doit être postérieure à la date de début.']],
                ], 422);
            }

            if ($calendrier = $anneeAcademique->calendrier()->first()) {
                $service = app(CalendrierAcademiqueService::class);
                $service->valider($service->presenter($calendrier), $dateDebut, $dateFin);
            }
            if ($donnees['active'] ?? false) {
                AnneeAcademique::query()->whereKeyNot($id)->where('active', true)
                    ->update(['active' => false, 'updated_by' => $request->user()->id]);
            }
            $anneeAcademique->update([...$donnees, 'updated_by' => $request->user()->id]);

            return response()->json([
                'message' => 'Année académique modifiée avec succès.',
                'annee_academique' => $anneeAcademique->fresh(),
            ]);
        });
    }

    #[OA\Delete(path: '/parametres/annees-academiques/{id}', operationId: 'supprimerAnneeAcademique', tags: ['Années académiques'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 403, description: 'Acc?s r?serv? aux comptes actifs ADMIN, SECRETAIRE_ACADEMIQUE et DIRECTION'), new OA\Response(response: 200, description: 'Année supprimée'), new OA\Response(response: 404, description: 'Introuvable')])]
    public function destroy(Request $request, int $id): JsonResponse
    {
        $anneeAcademique = AnneeAcademique::query()->findOrFail($id);
        $anneeAcademique->update(['deleted_by' => $request->user()->id]);
        $anneeAcademique->delete();

        return response()->json(['message' => 'Année académique supprimée avec succès.']);
    }
}
