<?php

namespace App\Http\Controllers\Api\V1\Enseignant;

use App\Models\AffectationEnseignant;
use App\Models\Cours;
use App\Models\CorrectionNote;
use App\Models\Creneau;
use App\Models\FeuilleNotes;
use App\Models\NoteCours;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class CorrectionNoteController extends \App\Http\Controllers\Api\V1\Administration\CorrectionNoteController
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'id_note' => ['sometimes', 'integer'],
            'id_seance' => ['sometimes', 'integer'],
            'statut' => ['sometimes', Rule::in(['en_attente', 'transmise_direction', 'autorisee', 'appliquee', 'rejetee'])],
            'search' => ['sometimes', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);
        $query = $this->corrections($request)
            ->when(isset($data['id_note']), fn ($q) => $q->where('id_note', $data['id_note']))
            ->when(isset($data['statut']), fn ($q) => $q->where('statut', $data['statut']))
            ->when(isset($data['id_seance']), fn ($q) => $q->whereHas('note.feuilleNotes', fn ($f) => $f->where('id_seance', $data['id_seance'])));
        if (isset($data['search']) && trim($data['search']) !== '') {
            $search = '%'.trim($data['search']).'%';
            $query->where(fn ($q) => $q
                ->whereHas('note.etudiant', fn ($e) => $e->where(fn ($e) => $e
                    ->where('nom', 'like', $search)->orWhere('prenoms', 'like', $search)->orWhere('matricule', 'like', $search)))
                ->orWhereHas('note.feuilleNotes.matiere', fn ($m) => $m->where('libelle', 'like', $search))
                ->orWhereHas('note.feuilleNotes.cours.module.matiere', fn ($m) => $m->where('libelle', 'like', $search))
                ->orWhereHas('note.feuilleNotes.cours', fn ($c) => $c->where('libelle', 'like', $search)));
        }
        $items = $query->latest('id')->paginate($data['per_page'] ?? 20);
        $items->through(fn ($correction) => $this->presenter($correction));

        return response()->json($items);
    }

    public function show(Request $request, int $id)
    {
        $correction = $this->corrections($request)->findOrFail($id);

        return response()->json([
            'correction' => $this->presenter($correction),
            'historique' => DB::table('traces_corrections_notes')->where('id_correction', $id)->orderBy('id')->get(),
        ]);
    }

    private function presenter(CorrectionNote $correction): array
    {
        $note = $correction->note;
        $feuille = $note?->feuilleNotes;

        return [
            ...$correction->toArray(),
            'modifiable' => $correction->statut === 'en_attente',
            'evaluation' => $note?->evaluation,
            'etudiant' => $note?->etudiant?->only(['id', 'matricule', 'nom', 'prenoms']),
            'feuille_notes' => $feuille ? [
                ...$feuille->only(['id', 'id_seance', 'id_annee_academique', 'id_promotion', 'id_cours', 'id_matiere', 'date_transmission']),
                'matiere' => ($feuille->cours?->module?->matiere ?? $feuille->matiere)?->only(['id', 'code', 'libelle']),
                'cours' => $feuille->cours?->only(['id', 'code', 'libelle']),
                'promotion' => $feuille->promotion ? [
                    ...$feuille->promotion->only(['id', 'num_promotion', 'annee_entree']),
                    'niveau' => $feuille->promotion->niveau?->only(['id', 'code', 'libelle']),
                ] : null,
            ] : null,
        ];
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'id_note' => ['prohibited'],
            'id_seance' => ['prohibited'],
            'note_proposee' => ['required', 'numeric', 'between:0,20', 'decimal:0,2'],
            'motif' => ['required', 'string', 'max:5000'],
        ]);
        $correction = DB::transaction(function () use ($request, $id, $data) {
            $correction = $this->corrections($request)->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($correction->statut !== 'en_attente') {
                throw ValidationException::withMessages(['statut' => 'Seule une demande en attente peut être modifiée.']);
            }
            $note = NoteCours::whereKey($correction->id_note)->lockForUpdate()->firstOrFail();
            $this->verifierAccesNote($request, $note);
            $this->verifierFeuille($note);
            if ($note->note !== $correction->note_initiale) {
                throw ValidationException::withMessages(['id_note' => 'La note a changé depuis la demande de correction.']);
            }
            if ((float) $data['note_proposee'] === $note->note) {
                throw ValidationException::withMessages(['note_proposee' => 'La nouvelle note doit être différente de la note actuelle.']);
            }
            $avant = $correction->only(['note_proposee', 'motif']);
            $correction->update($data);
            $this->tracer($correction, $request, 'modification', json_encode([
                'avant' => $avant, 'apres' => $correction->only(['note_proposee', 'motif']),
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return $correction;
        });

        return response()->json(['message' => 'Demande de correction modifiée.', 'correction' => $correction]);
    }

    public function store(Request $request)
    {
        if (! $request->exists('notes')) {
            return parent::store($request);
        }
        $data = $request->validate([
            'id_note' => ['prohibited'],
            'id_seance' => ['required', 'integer', 'exists:seances_cahier_texte,id'],
            'note_proposee' => ['prohibited'],
            'motif' => ['required', 'string', 'max:5000'],
            'notes' => ['required', 'array', 'min:1', 'max:100'],
            'notes.*' => ['required', 'array:id_note,note_proposee'],
            'notes.*.id_note' => ['required', 'integer', 'distinct'],
            'notes.*.note_proposee' => ['required', 'numeric', 'between:0,20', 'decimal:0,2'],
        ]);
        $corrections = DB::transaction(function () use ($request, $data) {
            // Verrouiller les notes dans un ordre stable pour les demandes concurrentes.
            $notes = collect($data['notes'])->sortBy('id_note');

            return $notes->map(fn ($note) => $this->creerDemande($request, [
                ...$note, 'motif' => $data['motif'],
                'id_seance' => $data['id_seance'],
            ]))->values();
        });

        return response()->json([
            'message' => 'Demandes de correction enregistrées.',
            'corrections' => $corrections,
            'nombre_demandes' => $corrections->count(),
        ], 201);
    }

    protected function corrections(Request $request)
    {
        return parent::corrections($request)->where('demande_par', $request->user()->id)
            ->with(['note.etudiant:id,matricule,nom,prenoms', 'note.feuilleNotes.matiere',
                'note.feuilleNotes.cours.module.matiere', 'note.feuilleNotes.promotion.niveau']);
    }

    protected function verifierAccesNote(Request $request, NoteCours $note): void
    {
        $feuille = FeuilleNotes::findOrFail($note->id_feuille_notes);
        if ($feuille->id_seance !== null) {
            abort_unless($feuille->seance?->enseignant_id === $request->user()->id, 404);
        }
        $matiere = $feuille->id_matiere;
        if ($feuille->id_cours !== null) {
            $cours = Cours::with('module.matiere')->findOrFail($feuille->id_cours);
            abort_unless($cours->module?->matiere, 404);
            $matiere = $cours->module->id_matiere;
        }
        $affectations = AffectationEnseignant::where('enseignant_id', $request->user()->id)
            ->where('id_matiere', $matiere)->whereDate('date_debut', '<=', today())
            ->where(fn ($q) => $q->whereNull('date_fin')->orWhereDate('date_fin', '>', today()))
            ->where(fn ($q) => $q->where('portee', 'matiere')
                ->when($feuille->id_cours !== null, fn ($q) => $q->orWhere(fn ($a) => $a->where('portee', 'cours')->where('id_cours', $feuille->id_cours))));
        abort_unless($affectations->exists(), 404);
        abort_unless(Creneau::where('enseignant_id', $request->user()->id)
            ->where('id_matiere', $matiere)->where('id_promotion', $feuille->id_promotion)
            ->when($feuille->id_cours !== null, fn ($q) => $q->where(fn ($c) => $c->whereNull('id_cours')->orWhere('id_cours', $feuille->id_cours)))
            ->whereHas('moduleCalendrier.calendrier', fn ($q) => $q->where('id_annee_academique', $feuille->id_annee_academique))->exists(), 404);
    }
}
