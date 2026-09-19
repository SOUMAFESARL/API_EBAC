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
    public function index(Request $request)
    {
        $data = $request->validate([
            'id_note' => ['sometimes', 'integer'],
            'statut' => ['sometimes', Rule::in(['en_attente', 'autorisee', 'appliquee', 'rejetee'])],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);

        return response()->json(CorrectionNote::query()
            ->when(isset($data['id_note']), fn ($q) => $q->where('id_note', $data['id_note']))
            ->when(isset($data['statut']), fn ($q) => $q->where('statut', $data['statut']))
            ->latest('id')->paginate($data['per_page'] ?? 20));
    }

    public function show(int $id)
    {
        return response()->json(['correction' => CorrectionNote::findOrFail($id),
            'historique' => DB::table('traces_corrections_notes')->where('id_correction', $id)->orderBy('id')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'id_note' => ['required', 'integer'],
            'note_proposee' => ['required', 'numeric', 'between:0,20', 'decimal:0,2'],
            'motif' => ['required', 'string', 'max:5000'],
        ]);
        $correction = DB::transaction(function () use ($request, $data) {
            $note = NoteCours::whereKey($data['id_note'])->lockForUpdate()->firstOrFail();
            $this->verifierFeuille($note);
            if (CorrectionNote::where('id_note', $note->id)->whereIn('statut', ['en_attente', 'autorisee'])->exists()) {
                throw ValidationException::withMessages(['id_note' => 'Une correction est déjà en cours pour cette note.']);
            }
            if ((float) $data['note_proposee'] === $note->note) {
                throw ValidationException::withMessages(['note_proposee' => 'La nouvelle note doit être différente de la note actuelle.']);
            }
            $correction = CorrectionNote::create([...$data, 'note_initiale' => $note->note,
                'demande_par' => $request->user()->id, 'statut' => 'en_attente']);
            $this->tracer($correction, $request, 'demande', $data['motif']);

            return $correction;
        });

        return response()->json(['message' => 'Demande de correction enregistrée.', 'correction' => $correction], 201);
    }

    public function autoriser(Request $request, int $id)
    {
        return $this->transition($request, $id, 'autorisee');
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

    private function transition(Request $request, int $id, string $statut)
    {
        $correction = DB::transaction(function () use ($request, $id, $statut) {
            $correction = CorrectionNote::whereKey($id)->lockForUpdate()->firstOrFail();
            $attendu = $statut === 'appliquee' ? 'autorisee' : 'en_attente';
            if ($correction->statut !== $attendu) {
                throw ValidationException::withMessages(['statut' => 'Cette transition est impossible depuis le statut actuel.']);
            }
            if ($statut === 'appliquee') {
                $note = NoteCours::whereKey($correction->id_note)->lockForUpdate()->firstOrFail();
                $feuille = $this->verifierFeuille($note);
                if ($note->note !== $correction->note_initiale) {
                    throw ValidationException::withMessages(['id_note' => 'La note a changé depuis la demande de correction.']);
                }
                $note->update(['note' => $correction->note_proposee]);
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

        return response()->json(['message' => 'Correction '.$statut.'.', 'correction' => $correction]);
    }

    private function verifierFeuille(NoteCours $note): FeuilleNotes
    {
        $feuille = FeuilleNotes::whereKey($note->id_feuille_notes)->lockForUpdate()->firstOrFail();
        if ($feuille->statut !== 'transmise') {
            throw ValidationException::withMessages(['id_note' => 'Seules les notes transmises peuvent faire l’objet d’une correction.']);
        }

        return $feuille;
    }

    private function tracer(CorrectionNote $correction, Request $request, string $action, ?string $details): void
    {
        DB::table('traces_corrections_notes')->insert(['id_correction' => $correction->id,
            'id_acteur' => $request->user()->id, 'action' => $action, 'details' => $details, 'created_at' => now()]);
    }
}
