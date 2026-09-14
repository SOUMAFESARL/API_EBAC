<?php

namespace App\Http\Controllers\Api\V1\Enseignant;

use App\Http\Controllers\Controller;
use App\Models\Creneau;
use App\Models\SeanceCahierTexte;
use App\Services\CahierTexteService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CahierTexteController extends Controller
{
    public function __construct(private CahierTexteService $service) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'recherche' => ['nullable', 'string', 'max:180'],
            'statut' => ['sometimes', Rule::in(['prevue', 'realisee', 'reportee', 'annulee'])],
            'id_promotion' => ['sometimes', 'integer', 'exists:promotions,id'],
            'date_debut' => ['sometimes', 'date'], 'date_fin' => ['sometimes', 'date', 'after_or_equal:date_debut'],
            'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $debut = $data['date_debut'] ?? now()->subDays(30)->toDateString();
        $fin = $data['date_fin'] ?? now()->addDays(7)->toDateString();
        $query = $this->query($request)->whereBetween('date_prevue', [$debut, $fin])
            ->when($data['statut'] ?? null, fn ($q, $statut) => $q->where('statut', $statut))
            ->when($data['id_promotion'] ?? null, fn ($q, $id) => $q->where('id_promotion', $id))
            ->when($data['recherche'] ?? null, function ($q, $recherche) {
                $terme = '%'.$recherche.'%';
                $q->where(fn ($s) => $s->where('theme_traite', 'like', $terme)
                    ->orWhereHas('cours', fn ($c) => $c->where('libelle', 'like', $terme))
                    ->orWhereHas('matiere', fn ($m) => $m->where('libelle', 'like', $terme))
                    ->orWhereHas('promotion', fn ($p) => $p->where('code', 'like', $terme)));
            })->orderByDesc('date_prevue')->orderByDesc('heure_debut_prevue');
        $items = $query->paginate($data['per_page'] ?? 15);

        return response()->json(['seances' => $items->getCollection()->map(fn ($item) => $this->presenter($item))->values(),
            'meta' => ['current_page' => $items->currentPage(), 'last_page' => $items->lastPage(), 'per_page' => $items->perPage(),
                'total' => $items->total(), 'from' => $items->firstItem(), 'to' => $items->lastItem()]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->valider($request);
        $creneau = Creneau::with('moduleCalendrier.publication')->where('enseignant_id', $request->user()->id)->findOrFail($data['id_creneau']);
        if ($creneau->moduleCalendrier?->publication?->statut !== 'publie') {
            throw ValidationException::withMessages(['id_creneau' => ['Le programme de ce créneau n’est pas publié.']]);
        }
        $date = CarbonImmutable::parse($data['date_prevue']);
        if ($date->lt($creneau->moduleCalendrier->date_debut) || $date->gt($creneau->moduleCalendrier->date_fin)) {
            throw ValidationException::withMessages(['date_prevue' => ['La date doit appartenir à la période du module.']]);
        }
        $seance = SeanceCahierTexte::firstOrNew(['id_creneau' => $creneau->id, 'date_prevue' => $date->toDateString()]);
        if ($seance->exists) {
            throw ValidationException::withMessages(['date_prevue' => ['Cette séance existe déjà dans le cahier de texte.']]);
        }
        $seance->fill([...$creneau->only(['id_module_calendrier', 'enseignant_id', 'id_niveau', 'id_matiere', 'id_cours', 'id_promotion', 'id_salle']),
            'heure_debut_prevue' => $creneau->heure_debut, 'heure_fin_prevue' => $creneau->heure_fin, 'source' => 'manuelle', 'created_by' => $request->user()->id]);
        $this->appliquer($seance, $data, $request->user()->id);

        return response()->json(['message' => 'Séance consignée avec succès.', 'seance' => $this->presenter($seance->fresh())], 201);
    }

    public function show(Request $request, int $seance): JsonResponse
    {
        return response()->json(['seance' => $this->presenter($this->query($request)->findOrFail($seance))]);
    }

    public function update(Request $request, int $seance): JsonResponse
    {
        $data = $this->valider($request, false);
        $item = $this->query($request)->findOrFail($seance);
        if (! $this->service->estModifiable($item)) {
            throw ValidationException::withMessages(['seance' => ['Le délai de modification de 48 heures est dépassé.']]);
        }
        $this->appliquer($item, $data, $request->user()->id);

        return response()->json(['message' => 'Séance mise à jour avec succès.', 'seance' => $this->presenter($item->fresh())]);
    }

    public function destroy(Request $request, int $seance): JsonResponse
    {
        $item = $this->query($request)->findOrFail($seance);
        if (! $this->service->estModifiable($item)) {
            throw ValidationException::withMessages(['seance' => ['Le délai de suppression de 48 heures est dépassé.']]);
        }
        $item->update(['deleted_by' => $request->user()->id]);
        $item->delete();

        return response()->json(['message' => 'Séance supprimée avec succès.']);
    }

    private function valider(Request $request, bool $creation = true): array
    {
        return $request->validate([
            'id_creneau' => [$creation ? 'required' : 'prohibited', 'integer', 'exists:creneaux,id'],
            'date_prevue' => [$creation ? 'required' : 'prohibited', 'date'],
            'statut' => ['required', Rule::in(['prevue', 'realisee', 'reportee', 'annulee'])],
            'date_effective' => ['nullable', 'date', 'before_or_equal:today'], 'heure_effective' => ['nullable', 'date_format:H:i'],
            'duree_reelle_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'theme_traite' => ['nullable', 'string', 'max:5000'], 'observations' => ['nullable', 'string', 'max:10000'],
            'supports_pedagogiques' => ['nullable', 'string', 'max:10000'], 'motif_annulation' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function appliquer(SeanceCahierTexte $seance, array $data, int $userId): void
    {
        if (! $this->service->estModifiable($seance)) {
            throw ValidationException::withMessages(['seance' => ['Le délai de consignation de 48 heures est dépassé.']]);
        }
        if ($data['statut'] === 'realisee' && (empty($data['date_effective']) || empty($data['heure_effective']) || empty($data['duree_reelle_minutes']) || empty($data['theme_traite']))) {
            throw ValidationException::withMessages(['statut' => ['Une séance réalisée exige date, heure, durée réelle et thème traité.']]);
        }
        if ($data['statut'] === 'annulee' && empty($data['motif_annulation'])) {
            throw ValidationException::withMessages(['motif_annulation' => ['Le motif d’annulation est obligatoire.']]);
        }
        $seance->fill([...$data, 'updated_by' => $userId]);
        $seance->save();
    }

    private function query(Request $request)
    {
        return SeanceCahierTexte::with(['matiere', 'cours.module', 'promotion', 'niveau', 'salle', 'moduleCalendrier'])
            ->where('enseignant_id', $request->user()->id)
            ->where(fn ($q) => $q->where('statut', '!=', 'prevue')
                ->orWhereHas('moduleCalendrier.publication', fn ($publication) => $publication->where('statut', 'publie')));
    }

    private function presenter(SeanceCahierTexte $seance): array
    {
        $modifiable = $this->service->estModifiable($seance);

        return [...$seance->toArray(), 'heure_debut_prevue' => substr($seance->heure_debut_prevue, 0, 5),
            'heure_fin_prevue' => $seance->heure_fin_prevue ? substr($seance->heure_fin_prevue, 0, 5) : null,
            'heure_effective' => $seance->heure_effective ? substr($seance->heure_effective, 0, 5) : null,
            'modifiable' => $modifiable, 'verrouillee' => ! $modifiable,
            'echeance_modification' => CarbonImmutable::parse($seance->date_prevue->toDateString().' '.$seance->heure_debut_prevue)->addHours(48)->toIso8601String()];
    }
}
