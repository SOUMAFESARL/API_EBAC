<?php

namespace App\Http\Controllers\Api\V1\Parametre;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Parametre\EnregistrerCalendrierRequest;
use App\Models\AnneeAcademique;
use App\Models\Creneau;
use App\Services\CalendrierAcademiqueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class CalendrierAcademiqueController extends Controller
{
    public function __construct(private CalendrierAcademiqueService $service) {}

    #[OA\Get(path: '/parametres/annees-academiques/{id}/calendrier', operationId: 'afficherCalendrierAcademique', tags: ['Calendrier académique'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 401, description: 'Authentification requise'), new OA\Response(response: 403, description: 'Acc?s r?serv? aux comptes actifs ADMIN, SECRETAIRE_ACADEMIQUE et DIRECTION'), new OA\Response(response: 200, description: 'Année et calendrier complet', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'annee_academique', ref: '#/components/schemas/AnneeAcademique'), new OA\Property(property: 'calendrier', ref: '#/components/schemas/CalendrierAcademique')])), new OA\Response(response: 404, description: 'Année ou calendrier introuvable')])]
    public function show(int $id): JsonResponse
    {
        $annee = AnneeAcademique::query()->findOrFail($id);

        return response()->json([
            'annee_academique' => $annee,
            'calendrier' => $this->service->presenter($annee->calendrier()->firstOrFail()),
        ]);
    }

    #[OA\Post(path: '/parametres/annees-academiques/{id}/calendrier', operationId: 'creerCalendrierAcademique', tags: ['Calendrier académique'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CalendrierPayload')), responses: [new OA\Response(response: 404, description: 'Ann?e introuvable'), new OA\Response(response: 401, description: 'Authentification requise'), new OA\Response(response: 403, description: 'Acc?s r?serv? aux comptes actifs ADMIN, SECRETAIRE_ACADEMIQUE et DIRECTION'), new OA\Response(response: 201, description: 'Calendrier créé', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'message', type: 'string', example: 'Calendrier acad?mique enregistr? avec succ?s.'), new OA\Property(property: 'calendrier', ref: '#/components/schemas/CalendrierAcademique')])), new OA\Response(response: 409, description: 'Calendrier déjà configuré'), new OA\Response(response: 422, description: 'Dates ou périodes invalides')])]
    public function store(EnregistrerCalendrierRequest $request, int $id): JsonResponse
    {
        return $this->enregistrer($request, $id, true);
    }

    #[OA\Put(path: '/parametres/annees-academiques/{id}/calendrier', operationId: 'remplacerCalendrierAcademique', tags: ['Calendrier académique'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CalendrierPayload')), responses: [new OA\Response(response: 401, description: 'Authentification requise'), new OA\Response(response: 403, description: 'Acc?s r?serv? aux comptes actifs ADMIN, SECRETAIRE_ACADEMIQUE et DIRECTION'), new OA\Response(response: 200, description: 'Calendrier remplacé intégralement', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'message', type: 'string', example: 'Calendrier acad?mique enregistr? avec succ?s.'), new OA\Property(property: 'calendrier', ref: '#/components/schemas/CalendrierAcademique')])), new OA\Response(response: 404, description: 'Introuvable'), new OA\Response(response: 422, description: 'Dates ou périodes invalides')])]
    public function update(EnregistrerCalendrierRequest $request, int $id): JsonResponse
    {
        return $this->enregistrer($request, $id, false);
    }

    private function enregistrer(EnregistrerCalendrierRequest $request, int $id, bool $creation): JsonResponse
    {
        return DB::transaction(function () use ($request, $id, $creation) {
            $annee = AnneeAcademique::query()->lockForUpdate()->findOrFail($id);
            $calendrier = $this->service->enregistrer($annee, $request->validated(), $request->user()->id, $creation);

            return response()->json([
                'message' => 'Calendrier académique enregistré avec succès.',
                'calendrier' => $this->service->presenter($calendrier),
            ], $creation ? 201 : 200);
        });
    }

    #[OA\Delete(path: '/parametres/annees-academiques/{id}/calendrier', operationId: 'supprimerCalendrierAcademique', tags: ['Calendrier académique'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 401, description: 'Authentification requise'), new OA\Response(response: 403, description: 'Acc?s r?serv? aux comptes actifs ADMIN, SECRETAIRE_ACADEMIQUE et DIRECTION'), new OA\Response(response: 200, description: 'Calendrier et périodes supprimés', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'message', type: 'string', example: 'Calendrier acad?mique supprim? avec succ?s.')])), new OA\Response(response: 404, description: 'Introuvable')])]
    public function destroy(int $id): JsonResponse
    {
        DB::transaction(function () use ($id) {
            $annee = AnneeAcademique::query()->lockForUpdate()->findOrFail($id);
            $calendrier = $annee->calendrier()->firstOrFail();
            if (Creneau::whereIn('id_module_calendrier', $calendrier->modules()->select('id'))->exists()) {
                throw ValidationException::withMessages(['modules' => ['Supprimez ou réaffectez les créneaux avant de supprimer le calendrier.']]);
            }
            $calendrier->delete();
        });

        return response()->json(['message' => 'Calendrier académique supprimé avec succès.']);
    }
}
