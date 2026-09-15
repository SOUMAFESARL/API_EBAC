<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CalendrierAcademiqueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProgrammePublieController extends Controller
{
    public function __construct(private CalendrierAcademiqueService $service) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_annee_academique' => ['sometimes', 'integer', Rule::exists('annees_academiques', 'id')->whereNull('deleted_at')],
            'id_module_calendrier' => ['prohibited'],
        ]);
        $annee = $this->service->anneePublication($data['id_annee_academique'] ?? null);
        $calendrier = $annee?->calendrier()->with('publication')->first();
        $publication = $calendrier?->publication;
        $publie = $publication?->statut === 'publie';

        return response()->json([
            'annee_academique' => $annee?->only(['id', 'libelle', 'date_debut', 'date_fin']),
            'publication' => [
                'statut' => $publication?->statut ?? 'non_publie',
                'version' => $publication?->version ?? 0,
                'date_publication' => $publication?->date_publication,
                'date_retrait' => $publication?->date_retrait,
            ],
            'calendrier' => $publie ? $this->service->presenter($calendrier) : null,
            'message' => $publie ? null : 'Le calendrier académique n’est pas encore disponible.',
        ]);
    }
}
