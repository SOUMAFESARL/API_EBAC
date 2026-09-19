<?php

namespace App\Http\Controllers\Api\V1\Enseignant;

use App\Http\Controllers\Controller;
use App\Models\AffectationEnseignant;
use App\Models\AnneeAcademique;
use App\Models\Cours;
use App\Models\Creneau;
use App\Models\Etudiant;
use App\Models\FeuilleNotes;
use App\Models\Promotion;
use App\Models\SeanceCahierTexte;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NoteController extends Controller
{
    private function coursAutorises(Request $request)
    {
        $affectations = AffectationEnseignant::where('enseignant_id', $request->user()->id)
            ->whereDate('date_debut', '<=', today())
            ->where(fn ($q) => $q->whereNull('date_fin')->orWhereDate('date_fin', '>', today()))->get();

        return Cours::with('module.matiere')->whereHas('module.matiere')->where(function ($q) use ($affectations) {
            $q->whereIn('id', $affectations->where('portee', 'cours')->pluck('id_cours'))
                ->orWhereHas('module', fn ($m) => $m->whereIn('id_matiere', $affectations->where('portee', 'matiere')->pluck('id_matiere')));
        });
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'id_annee_academique' => ['required', 'integer'],
            'id_promotion' => ['sometimes', 'integer'],
            'id_matiere' => ['sometimes', 'integer'],
        ]);
        AnneeAcademique::findOrFail($data['id_annee_academique']);
        $cours = $this->coursAutorises($request)->get();
        $creneaux = Creneau::with('promotion')->where('enseignant_id', $request->user()->id)
            ->whereHas('promotion')->whereHas('moduleCalendrier.calendrier', fn ($q) => $q->where('id_annee_academique', $data['id_annee_academique']))
            ->when($data['id_promotion'] ?? null, fn ($q, $id) => $q->where('id_promotion', $id))
            ->when($data['id_matiere'] ?? null, fn ($q, $id) => $q->where('id_matiere', $id))->get();
        $options = collect();
        foreach ($creneaux as $creneau) {
            foreach ($cours as $item) {
                if ($item->module->id_matiere == $creneau->id_matiere && (! $creneau->id_cours || $creneau->id_cours == $item->id)) {
                    $options->put($creneau->id_promotion.'_'.$item->id, [
                        'promotion' => $creneau->promotion->only(['id', 'code', 'num_promotion']),
                        'matiere' => $item->module->matiere->only(['id', 'code', 'libelle']),
                        'module' => $item->module->only(['id', 'libelle']),
                        'cours' => $item->only(['id', 'libelle', 'coefficient']),
                    ]);
                }
            }
        }

        return response()->json(['enseignements' => $options->values()]);
    }

    private function contexte(Request $request, int $cours): array
    {
        $data = $request->validate(['id_annee_academique' => ['required', 'integer'], 'id_promotion' => ['required', 'integer']]);
        AnneeAcademique::findOrFail($data['id_annee_academique']);
        $promotion = Promotion::findOrFail($data['id_promotion']);
        $item = $this->coursAutorises($request)->findOrFail($cours);
        abort_unless(Creneau::where('enseignant_id', $request->user()->id)->where('id_promotion', $promotion->id)
            ->where('id_matiere', $item->module->id_matiere)
            ->where(fn ($q) => $q->whereNull('id_cours')->orWhere('id_cours', $cours))
            ->whereHas('moduleCalendrier.calendrier', fn ($q) => $q->where('id_annee_academique', $data['id_annee_academique']))->exists(), 404);

        return [$item, $promotion, [...$data, 'id_cours' => $cours]];
    }

    private function presenter(Cours $cours, Promotion $promotion, array $cle): array
    {
        $etudiants = Etudiant::whereHas('inscriptions', fn ($q) => $q->where('id_promotion', $promotion->id))
            ->orderBy('nom')->orderBy('prenoms')->get();
        $seances = SeanceCahierTexte::with('feuillePresence.presences')->where('id_cours', $cours->id)
            ->where('id_promotion', $promotion->id)->where('statut', 'realisee')
            ->whereHas('moduleCalendrier.calendrier', fn ($q) => $q->where('id_annee_academique', $cle['id_annee_academique']))->get();
        $validees = $seances->filter(fn ($s) => $s->feuillePresence?->statut === 'validee');
        $ouverte = $seances->isNotEmpty() && $validees->count() === $seances->count()
            && $validees->every(fn ($s) => $etudiants->pluck('id')->diff($s->feuillePresence->presences->pluck('id_etudiant'))->isEmpty());
        $feuille = FeuilleNotes::with('notes')->where($cle)->first();
        $notes = $feuille?->notes->keyBy('id_etudiant') ?? collect();
        $moyennes = DB::table('notes_cours as n')->join('feuilles_notes as f', 'f.id', '=', 'n.id_feuille_notes')
            ->join('cours as c', 'c.id', '=', 'f.id_cours')->join('modules as m', 'm.id', '=', 'c.id_module')
            ->where('f.id_promotion', $promotion->id)->where('f.id_annee_academique', $cle['id_annee_academique'])
            ->where('m.id_matiere', $cours->module->id_matiere)->whereNull('c.deleted_at')->whereNull('m.deleted_at')
            ->selectRaw('n.id_etudiant, SUM(n.note * c.coefficient) / NULLIF(SUM(c.coefficient), 0) as moyenne')
            ->groupBy('n.id_etudiant')->pluck('moyenne', 'id_etudiant');
        $lignes = $etudiants->map(function ($etudiant) use ($validees, $ouverte, $notes, $moyennes) {
            $present = $ouverte && $validees->every(fn ($s) => $s->feuillePresence->presences
                ->contains(fn ($p) => $p->id_etudiant == $etudiant->id && $p->statut === 'present'));

            return [...$etudiant->only(['id', 'matricule', 'nom', 'prenoms']), 'evaluable' => $present,
                'statut_presence' => $ouverte ? ($present ? 'present' : 'absent') : 'en_attente',
                'id_note' => $notes->get($etudiant->id)?->id,
                'note' => $notes->get($etudiant->id)?->note,
                'moyenne_matiere' => isset($moyennes[$etudiant->id]) ? round((float) $moyennes[$etudiant->id], 2) : null,
                'statut_moyenne' => 'provisoire'];
        });

        return ['cours' => $cours->only(['id', 'libelle', 'coefficient']),
            'module' => $cours->module->only(['id', 'libelle']), 'matiere' => $cours->module->matiere->only(['id', 'libelle']),
            'promotion' => $promotion->only(['id', 'code', 'num_promotion']), 'id_annee_academique' => $cle['id_annee_academique'],
            'statut' => $feuille?->statut ?? 'brouillon', 'date_transmission' => $feuille?->date_transmission,
            'saisie_ouverte' => $ouverte && (! $feuille || $feuille->statut === 'brouillon'),
            'seances_realisees' => $seances->count(), 'presences_validees' => $validees->count(),
            'seances_a_relever' => $seances->diff($validees)->pluck('id')->values(),
            'notes_manquantes' => $lignes->where('evaluable', true)->whereNull('note')->count(), 'etudiants' => $lignes];
    }

    public function show(Request $request, int $cours)
    {
        return response()->json(['feuille_notes' => $this->presenter(...$this->contexte($request, $cours))]);
    }

    public function update(Request $request, int $cours)
    {
        return $this->enregistrer($request, $cours, false);
    }

    public function transmettre(Request $request, int $cours)
    {
        return $this->enregistrer($request, $cours, true);
    }

    private function enregistrer(Request $request, int $cours, bool $transmettre)
    {
        [$item, $promotion, $cle] = $this->contexte($request, $cours);
        $data = $request->validate([
            'notes' => [$transmettre ? 'sometimes' : 'required', 'array', 'min:1'],
            'notes.*.id_etudiant' => ['required', 'integer', 'distinct'],
            'notes.*.note' => ['present', 'nullable', 'numeric', 'between:0,20'],
        ]);
        DB::transaction(function () use ($request, $item, $promotion, $cle, $data, $transmettre) {
            // Serialise la creation de la feuille et les enregistrements concurrents.
            Promotion::whereKey($promotion->id)->lockForUpdate()->firstOrFail();
            $etat = $this->presenter($item, $promotion, $cle);
            if (! $etat['saisie_ouverte']) {
                throw ValidationException::withMessages(['notes' => ['Saisie fermée : présences non validées, aucune séance réalisée ou feuille déjà transmise.']]);
            }
            $eligibles = $etat['etudiants']->where('evaluable', true)->pluck('id');
            $feuille = FeuilleNotes::firstOrCreate($cle, ['updated_by' => $request->user()->id]);
            foreach ($data['notes'] ?? [] as $note) {
                if (! $eligibles->contains((int) $note['id_etudiant'])) {
                    throw ValidationException::withMessages(['notes' => ['Un étudiant absent ou hors promotion ne peut pas être noté.']]);
                }
                if ($note['note'] === null) {
                    $feuille->notes()->where('id_etudiant', $note['id_etudiant'])->delete();
                } else {
                    $feuille->notes()->updateOrCreate(['id_etudiant' => $note['id_etudiant']], ['note' => $note['note']]);
                }
            }
            $feuille->notes()->whereNotIn('id_etudiant', $eligibles)->delete();
            if ($transmettre && ($eligibles->isEmpty() || $feuille->notes()->count() !== $eligibles->count())) {
                throw ValidationException::withMessages(['notes' => ['Tous les étudiants évaluables doivent avoir une note avant transmission.']]);
            }
            $feuille->update(['updated_by' => $request->user()->id, ...($transmettre ? ['statut' => 'transmise', 'date_transmission' => now()] : [])]);
        });

        return response()->json(['message' => $transmettre ? 'Notes transmises au secrétariat.' : 'Notes enregistrées.',
            'feuille_notes' => $this->presenter($item, $promotion, $cle)]);
    }
}
