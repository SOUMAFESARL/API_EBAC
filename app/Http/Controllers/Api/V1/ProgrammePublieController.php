<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AnneeAcademique;
use App\Models\Creneau;
use App\Models\Etudiant;
use App\Models\ModuleCalendrier;
use App\Services\CreneauService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProgrammePublieController extends Controller
{
    private const JOURS = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];

    public function __construct(private CreneauService $service) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_annee_academique' => ['sometimes', 'integer', Rule::exists('annees_academiques', 'id')->whereNull('deleted_at')],
            'id_module_calendrier' => ['sometimes', 'integer', 'exists:modules_calendrier,id'],
        ]);
        $annee = $this->annee($data['id_annee_academique'] ?? null);
        if (! $annee) {
            return $this->vide('Aucune année académique active ou en cours.');
        }

        $modules = ModuleCalendrier::with('publication')->whereHas('calendrier', fn ($q) => $q->where('id_annee_academique', $annee->id))->orderBy('ordre')->get();
        $module = isset($data['id_module_calendrier']) ? $modules->firstWhere('id', $data['id_module_calendrier']) : $this->moduleCourant($modules);
        if (isset($data['id_module_calendrier']) && ! $module) {
            abort(422, 'Le module sélectionné n’appartient pas à cette année académique.');
        }
        if (! $module || $module->publication?->statut !== 'publie') {
            return response()->json([
                'annee_academique' => $annee->only(['id', 'libelle', 'date_debut', 'date_fin']),
                'module_calendrier' => $module?->only(['id', 'libelle', 'ordre', 'date_debut', 'date_fin']),
                'publication' => ['statut' => 'non_publie', 'version' => $module?->publication?->version ?? 0],
                'message' => 'Le programme n’est pas encore disponible.', 'jours' => [], 'creneaux' => [], 'nombre_creneaux' => 0, 'heures_hebdomadaires' => 0,
            ]);
        }

        $role = $request->user()->role?->code;
        $query = Creneau::query()->where('id_module_calendrier', $module->id);
        if ($role === 'ENSEIGNANT') {
            $query->where('enseignant_id', $request->user()->id);
        }
        if ($role === 'ETUDIANT') {
            $etudiant = Etudiant::where('user_id', $request->user()->id)->firstOrFail();
            $inscription = $etudiant->inscriptions()->with('promotion')->latest('date_inscription')->latest('id')->firstOrFail();
            $query->where('id_niveau', $inscription->promotion->id_niveau)
                ->where(fn ($q) => $q->whereNull('id_promotion')->orWhere('id_promotion', $inscription->id_promotion));
        }
        $creneaux = $query->with(['moduleCalendrier.calendrier', 'niveau', 'matiere', 'cours', 'promotion', 'enseignant', 'salle'])
            ->orderBy('jour')->orderBy('heure_debut')->orderBy('id')->get()->map(fn ($item) => $this->service->presenter($item));
        $jours = collect(self::JOURS)->map(function ($libelle, $numero) use ($creneaux) {
            $items = $creneaux->where('jour', $numero)->values();

            return $items->isEmpty() ? null : ['numero' => $numero, 'libelle' => $libelle, 'creneaux' => $items];
        })->filter()->values();

        return response()->json([
            'annee_academique' => $annee->only(['id', 'libelle', 'date_debut', 'date_fin']),
            'module_calendrier' => $module->only(['id', 'libelle', 'ordre', 'date_debut', 'date_fin']),
            'publication' => ['statut' => 'publie', 'version' => $module->publication->version, 'date_publication' => $module->publication->date_publication],
            'jours' => $jours, 'creneaux' => $creneaux, 'nombre_creneaux' => $creneaux->count(),
            'heures_hebdomadaires' => $creneaux->sum('duree_minutes') / 60,
        ]);
    }

    private function annee(?int $id): ?AnneeAcademique
    {
        if ($id) {
            return AnneeAcademique::find($id);
        }
        $date = now()->toDateString();

        return AnneeAcademique::where('active', true)->first() ?? AnneeAcademique::whereDate('date_debut', '<=', $date)->whereDate('date_fin', '>=', $date)->first();
    }

    private function moduleCourant($modules): ?ModuleCalendrier
    {
        $date = now()->toDateString();

        return $modules->first(fn ($item) => $item->date_debut->toDateString() <= $date && $item->date_fin->toDateString() >= $date)
            ?? $modules->first(fn ($item) => $item->date_debut->toDateString() > $date) ?? $modules->last();
    }

    private function vide(string $message): JsonResponse
    {
        return response()->json(['message' => $message, 'jours' => [], 'creneaux' => [], 'nombre_creneaux' => 0, 'heures_hebdomadaires' => 0]);
    }
}
