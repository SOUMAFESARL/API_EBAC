<?php

namespace App\Http\Controllers\Api\V1\Administration;

use App\Http\Controllers\Controller;
use App\Models\AnneeAcademique;
use App\Models\CalendrierAcademique;
use App\Services\CalendrierAcademiqueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PublicationProgrammeController extends Controller
{
    public function __construct(private CalendrierAcademiqueService $service) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_annee_academique' => ['sometimes', 'integer', Rule::exists('annees_academiques', 'id')->whereNull('deleted_at')],
            'id_module_calendrier' => ['prohibited'],
        ]);
        $annee = $this->service->anneePublication($data['id_annee_academique'] ?? null);
        $calendrier = $annee?->calendrier()->with('publication')->first();

        return response()->json([
            'annee_academique' => $annee?->only(['id', 'libelle', 'date_debut', 'date_fin']),
            'programmes' => $calendrier ? [$this->presenter($calendrier)] : [],
        ]);
    }

    public function publier(Request $request, int $calendrier_id): JsonResponse
    {
        $request->validate(['id_module_calendrier' => ['prohibited'], 'module_id' => ['prohibited']]);
        $calendrier = DB::transaction(function () use ($request, $calendrier_id) {
            $calendrier = $this->verrouillerCalendrier($calendrier_id);
            if (! $calendrier->modules()->exists()) {
                throw ValidationException::withMessages(['calendrier' => ['Ajoutez au moins un module au calendrier avant de le publier.']]);
            }
            $publication = $calendrier->publication()->firstOrNew();
            if ($publication->exists && $publication->statut === 'publie') {
                return $calendrier;
            }
            $publication->fill(['statut' => 'publie', 'version' => $publication->version + 1, 'date_publication' => now(),
                'date_retrait' => null, 'publie_par' => $request->user()->id, 'retire_par' => null])->save();

            return $calendrier;
        });

        return response()->json(['message' => 'Calendrier académique publié avec succès.', 'programme' => $this->presenter($calendrier)]);
    }

    public function retirer(Request $request, int $calendrier_id): JsonResponse
    {
        $calendrier = DB::transaction(function () use ($request, $calendrier_id) {
            $calendrier = $this->verrouillerCalendrier($calendrier_id);
            $publication = $calendrier->publication()->first();
            if (! $publication || $publication->statut !== 'publie') {
                throw ValidationException::withMessages(['calendrier' => ['Ce calendrier académique n’est pas publié.']]);
            }
            $publication->update(['statut' => 'non_publie', 'date_retrait' => now(), 'retire_par' => $request->user()->id]);

            return $calendrier;
        });

        return response()->json(['message' => 'Publication du calendrier académique retirée.', 'programme' => $this->presenter($calendrier)]);
    }

    private function verrouillerCalendrier(int $id): CalendrierAcademique
    {
        $calendrier = CalendrierAcademique::findOrFail($id);
        AnneeAcademique::lockForUpdate()->findOrFail($calendrier->id_annee_academique);

        return CalendrierAcademique::lockForUpdate()->findOrFail($id);
    }

    private function presenter(CalendrierAcademique $calendrier): array
    {
        $publication = $calendrier->publication()->first();

        return [
            'calendrier' => $this->service->presenter($calendrier),
            'statut' => $publication?->statut ?? 'non_publie',
            'version' => $publication?->version ?? 0,
            'date_publication' => $publication?->date_publication,
            'date_retrait' => $publication?->date_retrait,
            'visible_utilisateurs' => $publication?->statut === 'publie',
        ];
    }
}
