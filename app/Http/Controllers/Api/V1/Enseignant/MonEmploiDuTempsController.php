<?php

namespace App\Http\Controllers\Api\V1\Enseignant;

use App\Http\Controllers\Controller;
use App\Models\AnneeAcademique;
use App\Models\Creneau;
use App\Models\ModuleCalendrier;
use App\Services\CreneauService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MonEmploiDuTempsController extends Controller
{
    private const JOURS = [
        1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi',
        5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche',
    ];

    public function __construct(private CreneauService $service) {}

    public function __invoke(Request $request): JsonResponse
    {
        $filtres = $request->validate([
            'id_annee_academique' => ['sometimes', 'integer', Rule::exists('annees_academiques', 'id')->whereNull('deleted_at')],
            'id_module_calendrier' => ['sometimes', 'integer', 'exists:modules_calendrier,id'],
        ]);
        $annee = $this->annee($filtres['id_annee_academique'] ?? null);
        if (! $annee) {
            return response()->json([
                'message' => 'Aucune année académique active ou en cours.',
                'creneaux' => [], 'jours' => [], 'nombre_creneaux' => 0, 'heures_hebdomadaires' => 0,
            ]);
        }

        $modules = ModuleCalendrier::query()
            ->whereHas('calendrier', fn ($q) => $q->where('id_annee_academique', $annee->id))
            ->orderBy('ordre')->get();
        $module = isset($filtres['id_module_calendrier'])
            ? $modules->firstWhere('id', $filtres['id_module_calendrier'])
            : $this->moduleCourant($modules);
        if (isset($filtres['id_module_calendrier']) && ! $module) {
            abort(422, 'Le module calendrier sélectionné n’appartient pas à cette année académique.');
        }

        $creneaux = Creneau::query()
            ->where('enseignant_id', $request->user()->id)
            ->whereHas('moduleCalendrier.calendrier', fn ($q) => $q->where('id_annee_academique', $annee->id))
            ->when($module, fn ($q) => $q->where('id_module_calendrier', $module->id))
            ->with(['moduleCalendrier.calendrier', 'niveau', 'matiere', 'cours', 'promotion', 'enseignant', 'salle'])
            ->orderBy('jour')->orderBy('heure_debut')->orderBy('id')->get()
            ->map(fn (Creneau $creneau) => $this->service->presenter($creneau));
        $jours = collect(self::JOURS)->map(function (string $libelle, int $numero) use ($creneaux) {
            $items = $creneaux->where('jour', $numero)->values();

            return $items->isEmpty() ? null : ['numero' => $numero, 'libelle' => $libelle, 'creneaux' => $items];
        })->filter()->values();

        return response()->json([
            'annee_academique' => $annee->only(['id', 'libelle', 'date_debut', 'date_fin']),
            'module_calendrier' => $module?->only(['id', 'libelle', 'ordre', 'date_debut', 'date_fin']),
            'modules_disponibles' => $modules->map->only(['id', 'libelle', 'ordre', 'date_debut', 'date_fin']),
            'planning_hebdomadaire' => true,
            'jours' => $jours, 'creneaux' => $creneaux,
            'nombre_creneaux' => $creneaux->count(),
            'heures_hebdomadaires' => $creneaux->sum('duree_minutes') / 60,
        ]);
    }

    private function annee(?int $id): ?AnneeAcademique
    {
        if ($id) {
            return AnneeAcademique::find($id);
        }
        $date = now()->toDateString();

        return AnneeAcademique::where('active', true)->first()
            ?? AnneeAcademique::whereDate('date_debut', '<=', $date)->whereDate('date_fin', '>=', $date)->first();
    }

    private function moduleCourant($modules): ?ModuleCalendrier
    {
        $date = now()->toDateString();

        return $modules->first(fn ($module) => $module->date_debut->toDateString() <= $date && $module->date_fin->toDateString() >= $date)
            ?? $modules->first(fn ($module) => $module->date_debut->toDateString() > $date)
            ?? $modules->last();
    }
}
