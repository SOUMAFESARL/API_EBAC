<?php

namespace App\Http\Controllers\Api\V1\Administration;

use App\Http\Controllers\Controller;
use App\Models\AnneeAcademique;
use App\Models\Creneau;
use App\Models\ModuleCalendrier;
use App\Models\PublicationProgramme;
use App\Services\CahierTexteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PublicationProgrammeController extends Controller
{
    public function __construct(private CahierTexteService $cahierTexte) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_annee_academique' => ['sometimes', 'integer', Rule::exists('annees_academiques', 'id')->whereNull('deleted_at')],
            'id_module_calendrier' => ['sometimes', 'integer', 'exists:modules_calendrier,id'],
        ]);
        $annee = $this->annee($data['id_annee_academique'] ?? null);
        if (! $annee) {
            return response()->json(['message' => 'Aucune année académique active ou en cours.', 'programmes' => []]);
        }

        $modules = ModuleCalendrier::with(['publication', 'calendrier'])
            ->whereHas('calendrier', fn ($q) => $q->where('id_annee_academique', $annee->id))
            ->when($data['id_module_calendrier'] ?? null, fn ($q, int $id) => $q->whereKey($id))
            ->orderBy('ordre')->get();
        if (isset($data['id_module_calendrier']) && $modules->isEmpty()) {
            abort(422, 'Le module sélectionné n’appartient pas à cette année académique.');
        }

        return response()->json([
            'annee_academique' => $annee->only(['id', 'libelle', 'date_debut', 'date_fin']),
            'programmes' => $modules->map(fn ($module) => $this->presenter($module)),
        ]);
    }

    public function publier(Request $request, int $module_id): JsonResponse
    {
        $module = ModuleCalendrier::findOrFail($module_id);
        $publication = DB::transaction(function () use ($request, $module) {
            $nombre = Creneau::where('id_module_calendrier', $module->id)->lockForUpdate()->count();
            if ($nombre === 0) {
                throw ValidationException::withMessages(['module' => ['Ajoutez au moins un créneau avant de publier le programme.']]);
            }
            $publication = PublicationProgramme::query()->lockForUpdate()->firstOrNew(['id_module_calendrier' => $module->id]);
            if ($publication->exists && $publication->statut === 'publie') {
                return $publication;
            }
            $publication->fill(['statut' => 'publie', 'version' => $publication->version + 1, 'date_publication' => now(),
                'date_retrait' => null, 'publie_par' => $request->user()->id, 'retire_par' => null])->save();
            $this->cahierTexte->synchroniserModule($module);

            return $publication;
        });

        return response()->json(['message' => 'Programme publié avec succès.', 'programme' => $this->presenter($module->load('publication'))]);
    }

    public function retirer(Request $request, int $module_id): JsonResponse
    {
        $module = ModuleCalendrier::findOrFail($module_id);
        $publication = PublicationProgramme::where('id_module_calendrier', $module->id)->first();
        if (! $publication || $publication->statut !== 'publie') {
            throw ValidationException::withMessages(['module' => ['Ce programme n’est pas publié.']]);
        }
        $publication->update(['statut' => 'non_publie', 'date_retrait' => now(), 'retire_par' => $request->user()->id]);

        return response()->json(['message' => 'Programme retiré. Il n’est plus visible dans les espaces utilisateur.', 'programme' => $this->presenter($module->load('publication'))]);
    }

    private function presenter(ModuleCalendrier $module): array
    {
        $creneaux = Creneau::where('id_module_calendrier', $module->id);
        $publication = $module->publication;

        return [
            'module_calendrier' => $module->only(['id', 'libelle', 'ordre', 'date_debut', 'date_fin']),
            'statut' => $publication?->statut ?? 'non_publie', 'version' => $publication?->version ?? 0,
            'date_publication' => $publication?->date_publication, 'date_retrait' => $publication?->date_retrait,
            'nombre_creneaux' => (clone $creneaux)->count(),
            'nombre_matieres' => (clone $creneaux)->distinct()->count('id_matiere'),
            'nombre_cours' => (clone $creneaux)->whereNotNull('id_cours')->distinct()->count('id_cours'),
            'heures_hebdomadaires' => (clone $creneaux)->get()->sum(fn ($c) => ((int) substr($c->heure_fin, 0, 2) * 60 + (int) substr($c->heure_fin, 3, 2) - (int) substr($c->heure_debut, 0, 2) * 60 - (int) substr($c->heure_debut, 3, 2)) / 60),
            'visible_utilisateurs' => $publication?->statut === 'publie', 'nombre_conflits' => 0,
        ];
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
}
