<?php

namespace App\Http\Controllers\Api\V1\Administration;

use App\Models\FeuilleNotes;
use App\Models\Inscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CorrectionsGroupeesController extends CorrectionNoteController
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'search' => ['sometimes', 'string', 'max:255'],
            'statut' => ['sometimes', Rule::in(['en_attente', 'appliquee', 'rejetee', 'mixte'])],
            'id_promotion' => ['sometimes', 'integer'],
            'id_annee_academique' => ['sometimes', 'integer'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);
        $query = DB::table('notes_cours as n')->join('corrections_notes as c', 'c.id_note', '=', 'n.id')
            ->join('feuilles_notes as f', 'f.id', '=', 'n.id_feuille_notes')
            ->select('f.id as id_feuille_notes', 'n.evaluation')
            ->selectRaw('MAX(c.created_at) as date_demande')
            ->selectRaw("CASE WHEN SUM(CASE WHEN c.statut IN ('en_attente', 'autorisee', 'transmise_direction') THEN 1 ELSE 0 END) > 0 THEN 'en_attente' WHEN MIN(c.statut) = 'appliquee' AND MAX(c.statut) = 'appliquee' THEN 'appliquee' WHEN MIN(c.statut) = 'rejetee' AND MAX(c.statut) = 'rejetee' THEN 'rejetee' ELSE 'mixte' END as statut")
            ->groupBy('f.id', 'n.evaluation');
        foreach (['id_promotion', 'id_annee_academique'] as $field) {
            if (isset($data[$field])) {
                $query->where('f.'.$field, $data[$field]);
            }
        }
        // Filter whole groups, so searching never hides a correction inside a group.
        if (isset($data['search']) && trim($data['search']) !== '') {
            $search = '%'.trim($data['search']).'%';
            $feuilles = FeuilleNotes::query()->select('id')->where(fn ($q) => $q
                ->whereHas('matiere', fn ($m) => $m->where('libelle', 'like', $search))
                ->orWhereHas('cours', fn ($c) => $c->where('libelle', 'like', $search))
                ->orWhereHas('cours.module.matiere', fn ($m) => $m->where('libelle', 'like', $search))
                ->orWhereHas('promotion', fn ($p) => $p->where('num_promotion', 'like', $search)));
            $query->where(fn ($q) => $q->whereIn('f.id', $feuilles)->orWhere('n.evaluation', 'like', $search)
                ->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('notes_cours as sn')
                    ->join('corrections_notes as sc', 'sc.id_note', '=', 'sn.id')
                    ->whereColumn('sn.id_feuille_notes', 'f.id')
                    ->whereRaw('(sn.evaluation = n.evaluation OR (sn.evaluation IS NULL AND n.evaluation IS NULL))')
                    ->whereIn('sc.demande_par', User::select('id')->where(fn ($u) => $u->where('nom', 'like', $search)->orWhere('prenoms', 'like', $search)))));
        }
        $groups = DB::query()->fromSub($query, 'groupes');
        if (isset($data['statut'])) {
            $groups->where('statut', $data['statut']);
        }
        $items = $groups->orderByDesc('date_demande')->orderBy('id_feuille_notes')->orderBy('evaluation')
            ->paginate($data['per_page'] ?? 20);

        return response()->json([
            'feuilles_corrections' => $items->getCollection()->map(fn ($g) => $this->presenterGroupe($request, $g->id_feuille_notes, $g->evaluation))->values(),
            'meta' => ['total' => $items->total(), 'current_page' => $items->currentPage(), 'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(), 'from' => $items->firstItem(), 'to' => $items->lastItem()],
        ]);
    }

    public function show(Request $request, int $id)
    {
        $data = $this->validerGroupe($request);

        return response()->json(['feuille_corrections' => $this->presenterGroupe($request, $id, $data['evaluation'])]);
    }

    private function validerGroupe(Request $request): array
    {
        return $request->validate(['evaluation' => ['present', 'nullable', 'string', 'max:255']]);
    }

    private function demandes(Request $request, int $id, ?string $evaluation)
    {
        return $this->corrections($request)->whereHas('note', fn ($q) => $q
            ->where('id_feuille_notes', $id)->where('evaluation', $evaluation));
    }

    private function presenterGroupe(Request $request, int $id, ?string $evaluation): array
    {
        $feuille = FeuilleNotes::with(['matiere', 'cours.module.matiere', 'promotion.niveau', 'notes.etudiant:id,matricule,nom,prenoms'])->findOrFail($id);
        $demandes = $this->demandes($request, $id, $evaluation)->orderBy('id')->get();
        abort_if($demandes->isEmpty(), 404);
        $actives = $demandes->whereIn('statut', ['en_attente', 'autorisee', 'transmise_direction']);
        $rejetables = $demandes->whereIn('statut', ['en_attente', 'transmise_direction']);
        $statut = $actives->isNotEmpty() ? 'en_attente' : ($demandes->pluck('statut')->unique()->count() === 1 ? $demandes->first()->statut : 'mixte');
        $notes = $feuille->notes->filter(fn ($n) => $n->evaluation === $evaluation);
        $moyenne = $notes->avg('note');
        $editable = in_array($request->user()->role?->code, ['ADMIN', 'SECRETARIAT', 'SECRETAIRE_ACADEMIQUE'], true);
        $enseignants = $demandes->pluck('demandeur')->filter()->unique('id')->map(fn ($u) => $u->only(['id', 'nom', 'prenoms']))->values();
        $historique = DB::table('traces_corrections_notes')->whereIn('id_correction', $demandes->pluck('id'))->orderBy('id')->get();

        return [
            'id_feuille_notes' => $id, 'id_seance' => $feuille->id_seance, 'evaluation' => $evaluation,
            'id_annee_academique' => $feuille->id_annee_academique,
            'matiere' => ($feuille->cours?->module?->matiere ?? $feuille->matiere)?->only(['id', 'code', 'libelle']),
            'cours' => $feuille->cours?->only(['id', 'code', 'libelle']),
            'promotion' => $feuille->promotion?->only(['id', 'num_promotion']),
            'enseignant' => $enseignants->count() === 1 ? $enseignants->first() : null,
            'enseignants' => $enseignants,
            'date_demande' => $demandes->max('created_at'),
            'statut' => $statut,
            'statut_libelle' => match ($statut) {
                'en_attente' => 'À vérifier', 'appliquee' => 'Corrections appliquées', 'rejetee' => 'Rejetées', default => 'Traitement mixte',
            },
            'nombre_corrections' => $demandes->count(), 'corrections_en_attente' => $actives->count(),
            'ids_corrections_applicables' => $actives->pluck('id')->values(),
            'ids_corrections_rejetables' => $rejetables->pluck('id')->values(),
            'notes_saisies' => $notes->whereNotNull('note')->pluck('id_etudiant')->unique()->count(),
            'effectif' => Inscription::where('id_promotion', $feuille->id_promotion)->where('id_annee_academique', $feuille->id_annee_academique)->distinct()->count('id_etudiant'),
            'moyenne_cours' => $moyenne === null ? null : round($moyenne, 2),
            'notes' => $notes->map(fn ($n) => [...$n->only(['id', 'id_etudiant', 'evaluation', 'note']),
                'etudiant' => $n->etudiant?->only(['id', 'matricule', 'nom', 'prenoms']),
                'corrections' => $demandes->where('id_note', $n->id)->map(fn ($c) => $this->presenterAdministration($request, $c))->values(),
            ])->values(),
            'actions' => ['peut_appliquer' => $editable && $actives->isNotEmpty(), 'peut_rejeter' => $editable && $rejetables->isNotEmpty()],
            'historique' => $historique,
        ];
    }

    public function appliquer(Request $request, int $id)
    {
        return $this->traiterGroupe($request, $id, 'appliquee');
    }

    public function rejeter(Request $request, int $id)
    {
        $request->validate(['motif' => ['required', 'string', 'max:5000']]);

        return $this->traiterGroupe($request, $id, 'rejetee');
    }

    private function traiterGroupe(Request $request, int $id, string $statut)
    {
        $data = $this->validerGroupe($request);
        $selection = $request->validate([
            'ids_corrections' => ['required', 'array', 'min:1', 'max:100'],
            'ids_corrections.*' => ['required', 'integer', 'distinct'],
        ]);
        DB::transaction(function () use ($request, $id, $data, $selection, $statut) {
            $demandes = $this->demandes($request, $id, $data['evaluation'])
                ->whereIn('id', $selection['ids_corrections'])->orderBy('id_note')->orderBy('id')->lockForUpdate()->get();
            if ($demandes->count() !== count($selection['ids_corrections'])) {
                throw ValidationException::withMessages(['ids_corrections' => 'Chaque correction doit appartenir à cette feuille et cette évaluation.']);
            }
            foreach ($demandes as $correction) {
                $this->transition($request, $correction->id, $statut);
            }
        });

        return response()->json(['message' => $statut === 'appliquee' ? 'Corrections appliquées.' : 'Corrections rejetées.',
            'feuille_corrections' => $this->presenterGroupe($request, $id, $data['evaluation'])]);
    }
}
