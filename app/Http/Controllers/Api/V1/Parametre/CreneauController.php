<?php

namespace App\Http\Controllers\Api\V1\Parametre;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Parametre\EnregistrerCreneauRequest;
use App\Models\AnneeAcademique;
use App\Models\Cours;
use App\Models\Creneau;
use App\Models\Matiere;
use App\Models\ModuleCalendrier;
use App\Models\Niveau;
use App\Models\Promotion;
use App\Models\Salle;
use App\Models\User;
use App\Services\CreneauService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreneauController extends Controller
{
    public function __construct(private CreneauService $service) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_annee_academique' => ['required', 'integer', 'min:1'],
            'id_module_calendrier' => ['sometimes', 'integer', 'min:1'],
            'id_promotion' => ['sometimes', 'integer', 'min:1'],
            'id_niveau' => ['sometimes', 'integer', 'min:1'],
        ]);
        AnneeAcademique::findOrFail($data['id_annee_academique']);
        $query = Creneau::query()->whereHas('moduleCalendrier.calendrier', fn ($q) => $q->where('id_annee_academique', $data['id_annee_academique']));
        foreach (['id_module_calendrier', 'id_promotion', 'id_niveau'] as $key) {
            if (isset($data[$key])) {
                $query->where($key, $data[$key]);
            }
        }
        $creneaux = $query->with(['moduleCalendrier.calendrier', 'niveau', 'matiere', 'cours', 'promotion', 'enseignant', 'salle'])
            ->orderBy('jour')->orderBy('heure_debut')->orderBy('id')->get()->map(fn ($c) => $this->service->presenter($c));

        return response()->json(['creneaux' => $creneaux, 'nombre_creneaux' => $creneaux->count(), 'heures_hebdomadaires' => $creneaux->sum('duree_minutes') / 60]);
    }

    public function options(Request $request): JsonResponse
    {
        $data = $request->validate(['id_annee_academique' => ['required', 'integer', 'min:1'], 'id_niveau' => ['sometimes', 'integer', 'min:1'], 'id_matiere' => ['sometimes', 'integer', 'min:1']]);
        $annee = AnneeAcademique::findOrFail($data['id_annee_academique']);

        return response()->json([
            'modules_calendrier' => ModuleCalendrier::whereHas('calendrier', fn ($q) => $q->where('id_annee_academique', $annee->id))->orderBy('ordre')->get(['id', 'libelle', 'date_debut', 'date_fin']),
            'niveaux' => Niveau::orderBy('rang')->get(['id', 'libelle']),
            'promotions' => Promotion::when(isset($data['id_niveau']), fn ($q) => $q->where('id_niveau', $data['id_niveau']))->get(['id', 'code', 'id_niveau']),
            'matieres' => Matiere::where('active', true)->when(isset($data['id_niveau']), fn ($q) => $q->where('id_niveau', $data['id_niveau']))->get(['id', 'libelle', 'id_niveau']),
            'cours' => Cours::where('actif', true)->whereHas('module', fn ($q) => $q->when(isset($data['id_matiere']), fn ($m) => $m->where('id_matiere', $data['id_matiere'])))->get(['id', 'libelle', 'id_module']),
            'enseignants' => User::where('is_active', true)->where('statut', 'Actif')->whereHas('role', fn ($q) => $q->where('code', 'ENSEIGNANT'))->get(['id', 'nom', 'prenoms']),
            'salles' => Salle::where('statut', 'Actif')->orderBy('nom')->get(['id', 'nom', 'code']),
        ]);
    }

    public function store(EnregistrerCreneauRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $creneau = $this->service->enregistrer($request->validated(), $request->user()->id);

            return response()->json(['message' => 'Créneau créé avec succès.', 'creneau' => $this->service->presenter($creneau)], 201);
        }, 3);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['creneau' => $this->service->presenter($this->queryVisible()->findOrFail($id))]);
    }

    public function update(EnregistrerCreneauRequest $request, int $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id) {
            $creneau = $this->queryVisible()->lockForUpdate()->findOrFail($id);
            $creneau = $this->service->enregistrer($request->validated(), $request->user()->id, $creneau);

            return response()->json(['message' => 'Créneau modifié avec succès.', 'creneau' => $this->service->presenter($creneau)]);
        }, 3);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        DB::transaction(function () use ($request, $id) {
            $creneau = $this->queryVisible()->lockForUpdate()->findOrFail($id);
            $creneau->update(['deleted_by' => $request->user()->id]);
            $creneau->delete();
        });

        return response()->json(['message' => 'Créneau supprimé avec succès.']);
    }

    private function queryVisible(): Builder
    {
        return Creneau::query()->whereHas('moduleCalendrier.calendrier.anneeAcademique');
    }
}
