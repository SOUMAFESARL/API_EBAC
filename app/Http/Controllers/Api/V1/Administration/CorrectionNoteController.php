<?php

namespace App\Http\Controllers\Api\V1\Administration;

use App\Http\Controllers\Controller;
use App\Models\CorrectionNote;
use App\Models\FeuilleNotes;
use App\Models\NoteCours;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CorrectionNoteController extends Controller
{
    protected function corrections(Request $request)
    {
        return CorrectionNote::query()->with(['note.feuilleNotes.matiere', 'note.feuilleNotes.cours.module.matiere',
            'note.feuilleNotes.promotion.niveau', 'note.etudiant:id,matricule,nom,prenoms',
            'demandeur:id,nom,prenoms,id_role', 'demandeur.role:id,code,libelle']);
    }

    protected function verifierAccesNote(Request $request, NoteCours $note): void {}

    public function index(Request $request)
    {
        $data = $request->validate([
            'id_note' => ['sometimes', 'integer'],
            'id_seance' => ['sometimes', 'integer'],
            'demande_par' => ['sometimes', 'integer'],
            'search' => ['sometimes', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'statut' => ['sometimes', Rule::in(['en_attente', 'transmise_direction', 'autorisee', 'appliquee', 'rejetee'])],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);

        $query = $this->corrections($request)
            ->when(isset($data['id_note']), fn ($q) => $q->where('id_note', $data['id_note']))
            ->when(isset($data['demande_par']), fn ($q) => $q->where('demande_par', $data['demande_par']))
            ->when(isset($data['id_seance']), fn ($q) => $q->whereHas('note.feuilleNotes', fn ($f) => $f->where('id_seance', $data['id_seance'])))
            ->when(isset($data['statut']), fn ($q) => $q->where('statut', $data['statut']))
            ->when(isset($data['search']) && trim($data['search']) !== '', function ($q) use ($data) {
                $search = '%'.trim($data['search']).'%';
                $q->where(fn ($q) => $q
                    ->whereHas('demandeur', fn ($u) => $u->where(fn ($u) => $u->where('nom', 'like', $search)->orWhere('prenoms', 'like', $search)))
                    ->orWhereHas('note.etudiant', fn ($e) => $e->where(fn ($e) => $e->where('nom', 'like', $search)->orWhere('prenoms', 'like', $search)->orWhere('matricule', 'like', $search)))
                    ->orWhereHas('note.feuilleNotes.matiere', fn ($m) => $m->where('libelle', 'like', $search))
                    ->orWhereHas('note.feuilleNotes.cours.module.matiere', fn ($m) => $m->where('libelle', 'like', $search))
                    ->orWhereHas('note.feuilleNotes.cours', fn ($c) => $c->where('libelle', 'like', $search)));
            });
        $items = $query->latest('id')->paginate($data['per_page'] ?? 20);
        $items->through(fn ($c) => $this->presenterAdministration($request, $c));

        return response()->json($items);
    }

    public function show(Request $request, int $id)
    {
        return response()->json(['correction' => $this->presenterAdministration($request, $this->corrections($request)->findOrFail($id)),
            'historique' => DB::table('traces_corrections_notes')->where('id_correction', $id)->orderBy('id')->get()]);
    }

    private function presenterAdministration(Request $request, CorrectionNote $correction): array
    {
        $feuille = $correction->note?->feuilleNotes;
        $role = $request->user()->role?->code;
        $secretariat = in_array($role, ['SECRETARIAT', 'SECRETAIRE_ACADEMIQUE'], true);
        $direction = in_array($role, ['ADMIN', 'DIRECTION'], true);
        $decideur = ($secretariat && in_array($correction->statut, ['en_attente', 'transmise_direction'], true))
            || ($direction && in_array($correction->statut, ['en_attente', 'transmise_direction'], true));

        return [
            ...$correction->toArray(),
            'demandeur' => $correction->demandeur ? [
                ...$correction->demandeur->only(['id', 'nom', 'prenoms']),
                'role' => $correction->demandeur->role?->only(['code', 'libelle']),
            ] : null,
            'statut_libelle' => match ($correction->statut) {
                'en_attente', 'transmise_direction' => '? v?rifier',
                'autorisee' => 'Autoris?e',
                'appliquee' => 'Correction appliqu?e',
                'rejetee' => 'Rejet?e',
            },
            'note_actuelle' => $correction->note?->note,
            'evaluation' => $correction->note?->evaluation,
            'etudiant' => $correction->note?->etudiant?->only(['id', 'matricule', 'nom', 'prenoms']),
            'feuille_notes' => $feuille ? [
                ...$feuille->only(['id', 'id_seance', 'id_annee_academique', 'id_promotion', 'id_matiere', 'id_cours']),
                'matiere' => ($feuille->cours?->module?->matiere ?? $feuille->matiere)?->only(['id', 'code', 'libelle']),
                'cours' => $feuille->cours?->only(['id', 'code', 'libelle']),
                'promotion' => $feuille->promotion ? [
                    ...$feuille->promotion->only(['id', 'num_promotion']),
                    'niveau' => $feuille->promotion->niveau?->only(['id', 'code', 'libelle']),
                ] : null,
            ] : null,
            'actions' => [
                'peut_valider' => $decideur,
                'peut_rejeter' => $decideur,
                'peut_transmettre_direction' => false,
                'peut_appliquer' => in_array($role, ['ADMIN', 'SECRETARIAT', 'SECRETAIRE_ACADEMIQUE'], true) && in_array($correction->statut, ['en_attente', 'transmise_direction', 'autorisee'], true),
            ],
        ];
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'id_note' => ['required', 'integer'],
            'id_seance' => ['required', 'integer', 'exists:seances_cahier_texte,id'],
            'note_proposee' => ['required', 'numeric', 'between:0,20', 'decimal:0,2'],
            'motif' => ['required', 'string', 'max:5000'],
        ]);
        $correction = DB::transaction(fn () => $this->creerDemande($request, $data));

        return response()->json(['message' => 'Demande de correction enregistrée.', 'correction' => $correction], 201);
    }

    protected function creerDemande(Request $request, array $data): CorrectionNote
    {
        $note = NoteCours::whereKey($data['id_note'])->lockForUpdate()->firstOrFail();
        $this->verifierAccesNote($request, $note);
        $feuille = $this->verifierFeuille($note);
        if ((int) $data['id_seance'] !== $feuille->id_seance) {
            throw ValidationException::withMessages(['id_seance' => 'La note ne correspond pas a la seance indiquee.']);
        }
        unset($data['id_seance']);
        if (CorrectionNote::where('id_note', $note->id)->whereIn('statut', ['en_attente', 'transmise_direction', 'autorisee'])->exists()) {
            throw ValidationException::withMessages(['id_note' => 'Une correction est déjà en cours pour cette note.']);
        }
        if ((float) $data['note_proposee'] === $note->note) {
            throw ValidationException::withMessages(['note_proposee' => 'La nouvelle note doit être différente de la note actuelle.']);
        }
        $correction = CorrectionNote::create([...$data, 'note_initiale' => $note->note,
            'demande_par' => $request->user()->id, 'statut' => 'en_attente']);
        $this->tracer($correction, $request, 'demande', $data['motif']);

        return $correction;
    }

    public function autoriser(Request $request, int $id)
    {
        return $this->transition($request, $id, 'autorisee');
    }

    public function transmettreDirection(Request $request, int $id)
    {
        return $this->transition($request, $id, 'transmise_direction');
    }

    public function rejeter(Request $request, int $id)
    {
        $request->validate(['motif' => ['required', 'string', 'max:5000']]);

        return $this->transition($request, $id, 'rejetee');
    }

    public function appliquer(Request $request, int $id)
    {
        return $this->transition($request, $id, 'appliquee');
    }

    protected function transition(Request $request, int $id, string $statut)
    {
        $correction = DB::transaction(function () use ($request, $id, $statut) {
            $correction = $this->corrections($request)->whereKey($id)->lockForUpdate()->firstOrFail();
            $role = $request->user()->role?->code;
            $attendus = match ($statut) {
                'appliquee' => in_array($role, ['ADMIN', 'SECRETARIAT', 'SECRETAIRE_ACADEMIQUE'], true)
                    ? ['en_attente', 'transmise_direction', 'autorisee'] : ['autorisee'],
                'transmise_direction' => ['en_attente'],
                default => ['en_attente', 'transmise_direction'],
            };
            if ($statut === 'autorisee') {
                abort_unless(in_array($role, ['ADMIN', 'DIRECTION', 'SECRETARIAT', 'SECRETAIRE_ACADEMIQUE'], true), 403);
            }
            if (! in_array($correction->statut, $attendus, true)) {
                throw ValidationException::withMessages(['statut' => 'Cette transition est impossible depuis le statut actuel.']);
            }
            if ($statut === 'appliquee') {
                $note = NoteCours::whereKey($correction->id_note)->lockForUpdate()->firstOrFail();
                $this->verifierAccesNote($request, $note);
                $feuille = $this->verifierFeuille($note);
                if ($note->note !== $correction->note_initiale) {
                    throw ValidationException::withMessages(['id_note' => 'La note a changé depuis la demande de correction.']);
                }
                if ($correction->statut !== 'autorisee') {
                    $correction->fill(['autorisee_par' => $request->user()->id, 'autorisee_le' => now()]);
                    $this->tracer($correction, $request, 'autorisee', 'Verification et application par le secretariat ou ADMIN.');
                }
                $note->update(['note' => $correction->note_proposee]);
                if ($feuille->statut !== 'transmise') {
                    $feuille->changerStatut('transmise', $request->user()->id, 'correction_note', 'Une note corrigée doit être vérifiée à nouveau par le secrétariat académique.');
                }
                $feuille->update(['updated_by' => $request->user()->id]);
                $correction->fill(['note_finale' => $note->note, 'appliquee_par' => $request->user()->id, 'appliquee_le' => now()]);
            } elseif ($statut === 'autorisee') {
                $correction->fill(['autorisee_par' => $request->user()->id, 'autorisee_le' => now()]);
            }
            $correction->statut = $statut;
            $correction->save();
            $this->tracer($correction, $request, $statut, $statut === 'rejetee' ? $request->input('motif') : null);

            return $correction;
        });

        return response()->json(['message' => 'Correction '.$statut.'.', 'correction' =>
            in_array($request->user()->role?->code, ['ADMIN', 'SECRETARIAT', 'SECRETAIRE_ACADEMIQUE', 'DIRECTION'], true)
                ? $this->presenterAdministration($request, $this->corrections($request)->findOrFail($id)) : $correction]);
    }

    protected function verifierFeuille(NoteCours $note): FeuilleNotes
    {
        $feuille = FeuilleNotes::whereKey($note->id_feuille_notes)->lockForUpdate()->firstOrFail();
        if (! in_array($feuille->statut, ['transmise', 'validee_secretariat', 'transmise_direction', 'validee_direction', 'rejetee_direction'], true)) {
            throw ValidationException::withMessages(['id_note' => 'Seules les notes transmises peuvent faire l’objet d’une correction.']);
        }

        return $feuille;
    }

    protected function tracer(CorrectionNote $correction, Request $request, string $action, ?string $details): void
    {
        DB::table('traces_corrections_notes')->insert(['id_correction' => $correction->id,
            'id_acteur' => $request->user()->id, 'action' => $action, 'details' => $details, 'created_at' => now()]);
    }
}
