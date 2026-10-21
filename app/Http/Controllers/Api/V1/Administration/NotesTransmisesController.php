<?php

namespace App\Http\Controllers\Api\V1\Administration;

use App\Http\Controllers\Controller;
use App\Models\FeuilleNotes;
use App\Models\FeuillePresence;
use App\Models\Inscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class NotesTransmisesController extends Controller
{
    private function feuilles()
    {
        return FeuilleNotes::query()->whereIn('statut', FeuilleNotes::STATUTS_TRANSMIS)
            ->addSelect(['effectif_inscrits' => Inscription::selectRaw('COUNT(DISTINCT id_etudiant)')
                ->whereColumn('inscriptions.id_promotion', 'feuilles_notes.id_promotion')
                ->whereColumn('inscriptions.id_annee_academique', 'feuilles_notes.id_annee_academique')])
            ->withExists(['notes as correction_en_cours' => fn ($q) => $q->whereHas('corrections', fn ($c) => $c->whereIn('statut', ['en_attente', 'transmise_direction', 'autorisee']))])
            ->with(['notes.etudiant:id,matricule,nom,prenoms', 'derniereDecision', 'anneeAcademique', 'promotion', 'matiere', 'cours.module.matiere', 'enseignant:id,nom,prenoms', 'dernierModificateur:id,nom,prenoms']);
    }

    private function presenter(FeuilleNotes $feuille, Request $request): array
    {
        $role = $request->user()->role->code;
        $secretariat = in_array($role, ['ADMIN', 'SECRETARIAT', 'SECRETAIRE_ACADEMIQUE'], true);
        $direction = in_array($role, ['ADMIN', 'DIRECTION'], true);
        $controle = in_array($feuille->statut, ['transmise', 'validee_secretariat', 'rejetee_direction'], true);
        $bloquee = (bool) $feuille->correction_en_cours;
        $notesSaisies = $feuille->notes->whereNotNull('note');
        $evaluations = $feuille->notes->pluck('evaluation')->filter()->unique()->values();
        $effectif = (int) $feuille->effectif_inscrits;
        $etape = match ($feuille->statut) {
            'transmise_direction', 'rejetee_direction' => 3,
            'validee_direction' => 4,
            default => 2,
        };
        $libelle = match ($feuille->statut) {
            'transmise', 'validee_secretariat' => 'Contr?le secr?tariat',
            'transmise_direction' => 'Transmission direction',
            'validee_direction' => 'Verrouill?',
            'rejetee_secretariat' => 'Rejet? par le secr?tariat',
            'rejetee_direction' => 'Rejet? par la direction',
        };

        return [
            'evaluation' => $evaluations->count() === 1 ? $evaluations->first() : null,
            'evaluations' => $evaluations,
            'statut_libelle' => $libelle,
            'verrouillee' => $feuille->statut === 'validee_direction',
            'nombre_notes' => $feuille->notes->count(),
            'notes_saisies' => $notesSaisies->pluck('id_etudiant')->unique()->count(),
            'effectif' => $effectif,
            'moyenne_cours' => $notesSaisies->isEmpty() ? null : round($notesSaisies->avg('note'), 2),
            'correction_en_cours' => $bloquee,
            'etape_courante' => $etape,
            'etapes' => collect(['Saisie enseignant', 'Contr?le secr?tariat', 'Transmission direction', 'Valid? & verrouill?'])
                ->map(fn ($label, $index) => ['numero' => $index + 1, 'libelle' => $label,
                    'statut' => $index + 1 < $etape ? 'terminee' : ($index + 1 === $etape ? 'courante' : 'a_venir')])->all(),
            'actions' => [
                'peut_consulter' => true,
                'peut_transmettre_direction' => $secretariat && $controle && ! $bloquee,
                'peut_rejeter_secretariat' => $secretariat && $controle && ! $bloquee,
                'peut_valider_direction' => $direction && $feuille->statut === 'transmise_direction' && ! $bloquee,
                'peut_rejeter_direction' => $direction && $feuille->statut === 'transmise_direction' && ! $bloquee,
            ],
            ...$feuille->only(['id', 'id_annee_academique', 'id_promotion', 'id_matiere', 'id_cours', 'id_seance', 'statut', 'date_transmission']),
            'statut' => $feuille->statutPourRole($request->user()->role->code),
            'statut_workflow' => $feuille->statut,
            'motif' => $feuille->motifRejet(),
            'annee_academique' => $feuille->anneeAcademique?->only(['id', 'libelle']),
            'promotion' => $feuille->promotion?->only(['id', 'code', 'num_promotion']),
            'matiere' => ($feuille->matiere ?? $feuille->cours?->module?->matiere)?->only(['id', 'code', 'libelle']),
            'cours' => $feuille->cours?->only(['id', 'code', 'libelle']),
            'enseignant' => $feuille->enseignant?->only(['id', 'nom', 'prenoms']),
            'derniere_modification_par' => $feuille->dernierModificateur?->only(['id', 'nom', 'prenoms']),
            'historique' => $feuille->relationLoaded('historique') ? $feuille->historique : null,
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $statuts = FeuilleNotes::statutsPourRole($request->user()->role->code);
        $data = $request->validate([
            'search' => ['sometimes', 'string', 'max:255'],
            'id_seance' => ['sometimes', 'integer', 'exists:seances_cahier_texte,id'],
            'id_annee_academique' => ['sometimes', 'integer', 'exists:annees_academiques,id'],
            'id_promotion' => ['sometimes', 'integer', 'exists:promotions,id'],
            'id_matiere' => ['sometimes', 'integer', 'exists:matieres,id'],
            'statut' => ['sometimes', Rule::in(array_unique([...FeuilleNotes::STATUTS_TRANSMIS, ...array_values($statuts)]))],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $query = $this->feuilles()->withCount('notes')->with(['notes' => fn ($q) => $q->orderBy('id'), 'notes.etudiant:id,matricule,nom,prenoms']);
        if (isset($data['search']) && trim($data['search']) !== '') {
            $search = '%'.trim($data['search']).'%';
            $query->where(fn ($q) => $q
                ->whereHas('matiere', fn ($m) => $m->where('libelle', 'like', $search))
                ->orWhereHas('cours', fn ($c) => $c->where('libelle', 'like', $search))
                ->orWhereHas('cours.module.matiere', fn ($m) => $m->where('libelle', 'like', $search))
                ->orWhereHas('promotion', fn ($p) => $p->where('num_promotion', 'like', $search))
                ->orWhereHas('enseignant', fn ($u) => $u->where(fn ($u) => $u->where('nom', 'like', $search)->orWhere('prenoms', 'like', $search)))
                ->orWhereHas('notes', fn ($n) => $n->where('evaluation', 'like', $search)));
        }
        if (isset($data['statut'])) {
            $correspondances = array_keys($statuts, $data['statut'], true);
            $query->whereIn('statut', $correspondances ?: [$data['statut']]);
        }
        foreach (['id_seance', 'id_annee_academique', 'id_promotion'] as $champ) {
            if (isset($data[$champ])) {
                $query->where($champ, $data[$champ]);
            }
        }
        if (isset($data['id_matiere'])) {
            $query->where(fn ($q) => $q->where('id_matiere', $data['id_matiere'])
                ->orWhereHas('cours.module', fn ($m) => $m->where('id_matiere', $data['id_matiere'])));
        }
        $items = $query->orderByDesc('date_transmission')->orderByDesc('id')->paginate($data['per_page'] ?? 15);

        return response()->json([
            'feuilles_notes' => $items->getCollection()->map(fn ($f) => [
                ...$this->presenter($f, $request),
                'nombre_notes' => $f->notes_count,
                'notes' => $this->presenterNotes($f),
            ])->values(),
            'meta' => ['current_page' => $items->currentPage(), 'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(), 'total' => $items->total(), 'from' => $items->firstItem(), 'to' => $items->lastItem()],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $feuille = $this->feuilles()->with(['notes.etudiant:id,matricule,nom,prenoms', 'historique.acteur:id,nom,prenoms'])->findOrFail($id);

        return response()->json(['feuille_notes' => [
            ...$this->presenter($feuille, $request),
            'notes' => $this->presenterNotes($feuille),
        ]]);
    }

    private function presenterNotes(FeuilleNotes $feuille): array
    {
        return $feuille->notes->map(fn ($note) => [
            ...$note->only(['id', 'id_etudiant', 'evaluation', 'note']),
            'etudiant' => $note->etudiant?->only(['id', 'matricule', 'nom', 'prenoms']),
        ])->values()->all();
    }

    public function validerSecretariat(Request $request, int $id): JsonResponse
    {
        return $this->transition($request, $id, ['transmise', 'rejetee_direction'], 'validee_secretariat', 'validation_secretariat');
    }

    public function rejeterSecretariat(Request $request, int $id): JsonResponse
    {
        return $this->transition($request, $id, ['transmise', 'validee_secretariat', 'rejetee_direction'], 'rejetee_secretariat', 'rejet_secretariat', true);
    }

    public function transmettreDirection(Request $request, int $id): JsonResponse
    {
        return $this->transition($request, $id, ['transmise', 'validee_secretariat', 'rejetee_direction'], 'transmise_direction', 'transmission_direction');
    }

    public function validerDirection(Request $request, int $id): JsonResponse
    {
        return $this->transition($request, $id, ['transmise_direction'], 'validee_direction', 'validation_direction');
    }

    public function rejeterDirection(Request $request, int $id): JsonResponse
    {
        return $this->transition($request, $id, ['transmise_direction'], 'rejetee_direction', 'rejet_direction', true);
    }

    private function transition(Request $request, int $id, array $attendus, string $statut, string $action, bool $rejet = false): JsonResponse
    {
        $data = $request->validate(['motif' => [$rejet ? 'required' : 'sometimes', 'string', 'max:5000']]);
        $feuille = DB::transaction(function () use ($request, $id, $attendus, $statut, $action, $data) {
            $feuille = FeuilleNotes::whereKey($id)->lockForUpdate()->firstOrFail();
            if (! in_array($feuille->statut, $attendus, true)) {
                throw ValidationException::withMessages(['statut' => 'Cette action est impossible depuis le statut actuel de la feuille.']);
            }
            if ($feuille->notes()->whereHas('corrections', fn ($q) => $q->whereIn('statut', ['en_attente', 'transmise_direction', 'autorisee']))->exists()) {
                throw ValidationException::withMessages(['notes' => 'Une correction est en cours. Traitez-la avant de poursuivre le circuit de validation.']);
            }
            $feuille->changerStatut($statut, $request->user()->id, $action, $data['motif'] ?? null);
            if (in_array($statut, ['rejetee_secretariat', 'rejetee_direction'], true) && $feuille->id_seance !== null) {
                FeuillePresence::where('id_seance', $feuille->id_seance)->update([
                    'statut' => 'brouillon', 'date_validation' => null,
                    'validee_par' => null, 'updated_by' => $request->user()->id,
                ]);
            }

            return $feuille;
        });
        $feuille = $this->feuilles()->findOrFail($feuille->id);
        $feuille->load(['anneeAcademique', 'promotion', 'matiere', 'cours.module.matiere', 'dernierModificateur:id,nom,prenoms', 'historique.acteur:id,nom,prenoms']);

        return response()->json(['message' => 'Statut de la feuille mis à jour.', 'feuille_notes' => $this->presenter($feuille, $request)]);
    }
}
