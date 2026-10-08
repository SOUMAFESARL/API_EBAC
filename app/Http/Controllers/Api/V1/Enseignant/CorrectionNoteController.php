<?php

namespace App\Http\Controllers\Api\V1\Enseignant;

use App\Models\AffectationEnseignant;
use App\Models\Cours;
use App\Models\Creneau;
use App\Models\FeuilleNotes;
use App\Models\NoteCours;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CorrectionNoteController extends \App\Http\Controllers\Api\V1\Administration\CorrectionNoteController
{
    public function store(Request $request)
    {
        if (! $request->exists('notes')) {
            return parent::store($request);
        }
        $data = $request->validate([
            'id_note' => ['prohibited'],
            'id_seance' => ['sometimes', 'integer', 'exists:seances_cahier_texte,id'],
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
                ...(isset($data['id_seance']) ? ['id_seance' => $data['id_seance']] : []),
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
        return parent::corrections($request)->where('demande_par', $request->user()->id);
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
