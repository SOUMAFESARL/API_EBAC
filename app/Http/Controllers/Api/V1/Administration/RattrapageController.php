<?php

namespace App\Http\Controllers\Api\V1\Administration;

use App\Http\Controllers\Controller;
use App\Models\CoursAFaire;
use App\Models\Presence;
use App\Models\ProgrammationRattrapage;
use App\Models\SeanceCahierTexte;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RattrapageController extends Controller
{
    private const RELATIONS = ['coursAFaire.etudiant', 'coursAFaire.matiere', 'coursAFaire.cours',
        'coursAFaire.seance', 'enseignant:id,nom,prenoms', 'salle', 'session.promotion'];

    private function absences()
    {
        return CoursAFaire::where('motif', 'absence')
            ->whereHas('seance', fn ($q) => $q->where('statut', 'realisee'))
            ->whereHas('seance.feuillePresence', fn ($q) => $q->where('statut', 'validee'))
            ->whereExists(function ($q) {
                $q->selectRaw('1')->from('presences')->join('feuilles_presence', 'feuilles_presence.id', '=', 'presences.id_feuille_presence')
                    ->whereColumn('feuilles_presence.id_seance', 'cours_a_faire.id_seance')
                    ->whereColumn('presences.id_etudiant', 'cours_a_faire.id_etudiant')->where('presences.statut', 'absent');
            });
    }

    public function plan(Request $request)
    {
        $data = $request->validate([
            'search' => ['sometimes', 'string', 'max:255'],
            'id_promotion' => ['sometimes', 'integer'],
            'id_etudiant' => ['sometimes', 'integer'],
            'id_matiere' => ['sometimes', 'integer'],
            'id_cours' => ['sometimes', 'integer'],
            'id_seance' => ['sometimes', 'integer'],
            'statut' => ['sometimes', Rule::in(['non_planifie', 'inscrit', 'effectue'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);
        $query = $this->absences()->whereIn('statut', ['a_faire', 'termine'])
            ->with(['etudiant:id,matricule,nom,prenoms', 'matiere', 'cours.module', 'seance.promotion.niveau', 'rattrapage.session.promotion']);
        foreach (['id_etudiant', 'id_matiere', 'id_cours', 'id_seance'] as $champ) {
            if (isset($data[$champ])) {
                $query->where($champ, $data[$champ]);
            }
        }
        if (isset($data['id_promotion'])) {
            $query->whereHas('seance', fn ($q) => $q->where('id_promotion', $data['id_promotion']));
        }
        if (isset($data['search']) && trim($data['search']) !== '') {
            $search = '%'.trim($data['search']).'%';
            $query->where(fn ($q) => $q
                ->whereHas('etudiant', fn ($e) => $e->where(fn ($e) => $e->where('nom', 'like', $search)->orWhere('prenoms', 'like', $search)->orWhere('matricule', 'like', $search)))
                ->orWhereHas('matiere', fn ($m) => $m->where('libelle', 'like', $search))
                ->orWhereHas('cours', fn ($c) => $c->where('libelle', 'like', $search)));
        }
        $nonPlanifies = (clone $query)->where('statut', 'a_faire')->whereDoesntHave('rattrapage', fn ($q) => $q->whereIn('statut', ['programme', 'realise']))->count();
        $inscrits = (clone $query)->where('statut', 'a_faire')->whereHas('rattrapage', fn ($q) => $q->where('statut', 'programme'))->count();
        $effectues = (clone $query)->where(fn ($q) => $q->where('statut', 'termine')->orWhereHas('rattrapage', fn ($r) => $r->where('statut', 'realise')))->count();
        if (isset($data['statut'])) {
            match ($data['statut']) {
                'non_planifie' => $query->where('statut', 'a_faire')->whereDoesntHave('rattrapage', fn ($q) => $q->whereIn('statut', ['programme', 'realise'])),
                'inscrit' => $query->where('statut', 'a_faire')->whereHas('rattrapage', fn ($q) => $q->where('statut', 'programme')),
                'effectue' => $query->where(fn ($q) => $q->where('statut', 'termine')->orWhereHas('rattrapage', fn ($r) => $r->where('statut', 'realise'))),
            };
        }
        $items = $query->latest('id')->paginate($data['per_page'] ?? 20);
        $items->through(fn ($c) => [
            ...$c->toArray(),
            'statut_plan' => $c->statut === 'termine' || $c->rattrapage?->statut === 'realise' ? 'effectue' : ($c->rattrapage?->statut === 'programme' ? 'inscrit' : 'non_planifie'),
            'promotion_origine' => $c->seance?->promotion,
        ]);
        $total = $nonPlanifies + $inscrits + $effectues;

        return response()->json([
            ...$items->toArray(),
            'statistiques' => ['non_planifies' => $nonPlanifies, 'inscrits' => $inscrits, 'effectues' => $effectues,
                'part_non_planifiee' => $nonPlanifies + $inscrits > 0 ? round(100 * $nonPlanifies / ($nonPlanifies + $inscrits)) : 0, 'total' => $total],
        ]);
    }

    private function sessionsEligibles(CoursAFaire $cours)
    {
        return SeanceCahierTexte::where('id_matiere', $cours->id_matiere)
            ->where('id_cours', $cours->id_cours)->where('statut', 'prevue')
            ->whereKeyNot($cours->id_seance)->whereDate('date_prevue', '>=', today())
            ->whereDate('date_prevue', '>=', $cours->seance?->date_effective ?? $cours->seance?->date_prevue ?? today())
            ->whereNotNull('heure_debut_prevue')->whereNotNull('heure_fin_prevue')
            ->whereHas('enseignant', fn ($q) => $q->where('is_active', true)->whereHas('role', fn ($r) => $r->where('code', 'ENSEIGNANT')));
    }

    public function sessions(int $id)
    {
        return DB::transaction(function () use ($id) {
            $this->verifierAbsence($id);
            $cours = CoursAFaire::with('seance')->findOrFail($id);

            return response()->json(['sessions' => $this->sessionsEligibles($cours)
                ->with(['promotion.niveau', 'enseignant:id,nom,prenoms', 'salle'])
                ->orderBy('date_prevue')->orderBy('heure_debut_prevue')->get()]);
        });
    }

    public function annuler(Request $request, int $id)
    {
        $item = DB::transaction(function () use ($request, $id) {
            $item = ProgrammationRattrapage::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($item->statut !== 'programme') {
                throw ValidationException::withMessages(['statut' => 'Seul un rattrapage programme peut etre annule.']);
            }
            $item->update(['statut' => 'annule', 'updated_by' => $request->user()->id]);

            return $item;
        });

        return response()->json(['message' => 'Rattrapage annule.', 'rattrapage' => $item->load(self::RELATIONS)]);
    }

    public function index(Request $request)
    {
        $data = $this->filtres($request);
        $query = ProgrammationRattrapage::with(self::RELATIONS);
        foreach (['id_etudiant', 'id_seance', 'id_cours', 'id_matiere'] as $champ) {
            if (isset($data[$champ])) {
                $query->whereHas('coursAFaire', fn ($q) => $q->where($champ, $data[$champ]));
            }
        }
        foreach (['statut', 'enseignant_id', 'date_prevue'] as $champ) {
            if (isset($data[$champ])) {
                $query->where($champ, $data[$champ]);
            }
        }

        return response()->json($query->orderBy('date_prevue')->orderBy('heure_debut')->paginate($data['per_page'] ?? 20));
    }

    public function coursARattraper(Request $request)
    {
        $data = $this->filtres($request);
        $query = CoursAFaire::where('statut', 'a_faire')->where('motif', 'absence')
            ->whereHas('seance', fn ($q) => $q->where('statut', 'realisee'))
            ->whereHas('seance.feuillePresence', fn ($q) => $q->where('statut', 'validee'))
            ->whereExists(function ($q) {
                $q->selectRaw('1')->from('presences')->join('feuilles_presence', 'feuilles_presence.id', '=', 'presences.id_feuille_presence')
                    ->whereColumn('feuilles_presence.id_seance', 'cours_a_faire.id_seance')
                    ->whereColumn('presences.id_etudiant', 'cours_a_faire.id_etudiant')->where('presences.statut', 'absent');
            })->with(['etudiant', 'matiere', 'cours', 'seance', 'rattrapage']);
        foreach (['id_etudiant', 'id_seance', 'id_cours', 'id_matiere'] as $champ) {
            if (isset($data[$champ])) {
                $query->where($champ, $data[$champ]);
            }
        }

        return response()->json($query->latest('id')->paginate($data['per_page'] ?? 20));
    }

    public function show(int $id)
    {
        return response()->json(['rattrapage' => ProgrammationRattrapage::with(self::RELATIONS)->findOrFail($id)]);
    }

    public function store(Request $request)
    {
        $item = DB::transaction(function () use ($request) {
            $data = $this->payload($request);
            $this->verifierAbsence($data['id_cours_a_faire']);
            if (ProgrammationRattrapage::where('id_cours_a_faire', $data['id_cours_a_faire'])->exists()) {
                throw ValidationException::withMessages(['id_cours_a_faire' => 'Un rattrapage existe deja pour cette absence. Modifiez sa programmation.']);
            }

            return ProgrammationRattrapage::create(['statut' => 'programme', ...$data, 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
        });

        return response()->json(['message' => 'Rattrapage programme.', 'rattrapage' => $item->load(self::RELATIONS)], 201);
    }

    public function update(Request $request, int $id)
    {
        $item = ProgrammationRattrapage::findOrFail($id);
        $item = DB::transaction(function () use ($request, $item) {
            $this->verifierAbsence($item->id_cours_a_faire);
            $item = ProgrammationRattrapage::whereKey($item->id)->lockForUpdate()->firstOrFail();
            if ($item->statut === 'realise') {
                throw ValidationException::withMessages(['statut' => 'Un rattrapage realise ne peut plus etre reprogramme.']);
            }
            $data = $this->payload($request, $item);
            $item->update([...$data, 'updated_by' => $request->user()->id]);

            return $item;
        });

        return response()->json(['message' => 'Rattrapage modifie.', 'rattrapage' => $item->load(self::RELATIONS)]);
    }

    public function destroy(int $id)
    {
        ProgrammationRattrapage::findOrFail($id)->delete();

        return response()->json(['message' => 'Programmation supprimee.']);
    }

    private function verifierAbsence(int $id): void
    {
        $cours = CoursAFaire::whereKey($id)->lockForUpdate()->firstOrFail();
        $absence = Presence::where('id_etudiant', $cours->id_etudiant)->where('statut', 'absent')
            ->whereHas('feuille', fn ($q) => $q->where('id_seance', $cours->id_seance)->where('statut', 'validee')
                ->whereHas('seance', fn ($s) => $s->where('statut', 'realisee')))->exists();
        if ($cours->statut !== 'a_faire' || $cours->motif !== 'absence' || ! $absence) {
            throw ValidationException::withMessages(['id_cours_a_faire' => 'Une absence validee sur une seance realisee avec un cours restant a faire est requise.']);
        }
    }

    private function payload(Request $request, ?ProgrammationRattrapage $item = null): array
    {
        if ($request->exists('id_seance_rattrapage')) {
            $data = $request->validate([
                'id_cours_a_faire' => $item ? ['prohibited'] : ['required', 'integer', 'exists:cours_a_faire,id'],
                'id_seance_rattrapage' => ['required', 'integer'],
                'date_prevue' => ['prohibited'], 'heure_debut' => ['prohibited'], 'heure_fin' => ['prohibited'],
                'enseignant_id' => ['prohibited'], 'id_salle' => ['prohibited'], 'statut' => ['prohibited'],
                'observations' => ['sometimes', 'nullable', 'string', 'max:5000'],
            ]);
            $cours = CoursAFaire::with('seance')->findOrFail($item?->id_cours_a_faire ?? $data['id_cours_a_faire']);
            $session = $this->sessionsEligibles($cours)->lockForUpdate()->find($data['id_seance_rattrapage']);
            if (! $session || $session->heure_fin_prevue <= $session->heure_debut_prevue) {
                throw ValidationException::withMessages(['id_seance_rattrapage' => 'Choisissez une seance future du meme enseignement avec des horaires valides.']);
            }

            return [...$data, 'date_prevue' => $session->date_prevue->toDateString(),
                'heure_debut' => $session->heure_debut_prevue, 'heure_fin' => $session->heure_fin_prevue,
                'enseignant_id' => $session->enseignant_id, 'id_salle' => $session->id_salle, 'statut' => 'programme'];
        }
        $data = $request->validate([
            'id_cours_a_faire' => $item ? ['prohibited'] : ['required', 'integer', 'exists:cours_a_faire,id'],
            'date_prevue' => ['required', 'date_format:Y-m-d'],
            'heure_debut' => ['required', 'date_format:H:i'],
            'heure_fin' => ['required', 'date_format:H:i', 'after:heure_debut'],
            'enseignant_id' => ['required', 'integer', 'exists:users,id'],
            'id_salle' => ['sometimes', 'nullable', 'integer', 'exists:salles,id'],
            'statut' => ['sometimes', Rule::in(['programme', 'realise', 'annule'])],
            'observations' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);
        if (! User::whereKey($data['enseignant_id'])->where('is_active', true)->whereHas('role', fn ($q) => $q->where('code', 'ENSEIGNANT'))->exists()) {
            throw ValidationException::withMessages(['enseignant_id' => 'Un enseignant actif est requis.']);
        }
        $cours = CoursAFaire::with('seance')->findOrFail($item?->id_cours_a_faire ?? $data['id_cours_a_faire']);
        $dateOrigine = $cours->seance?->date_effective ?? $cours->seance?->date_prevue;
        if (! $dateOrigine || $data['date_prevue'] < $dateOrigine->toDateString()) {
            throw ValidationException::withMessages(['date_prevue' => 'Le rattrapage doit etre programme a partir de la date de la seance manquee.']);
        }

        return [...$data, 'id_seance_rattrapage' => null];
    }

    private function filtres(Request $request): array
    {
        return $request->validate([
            'id_etudiant' => ['sometimes', 'integer', 'exists:etudiants,id'],
            'id_seance' => ['sometimes', 'integer', 'exists:seances_cahier_texte,id'],
            'id_cours' => ['sometimes', 'integer', 'exists:cours,id'],
            'id_matiere' => ['sometimes', 'integer', 'exists:matieres,id'],
            'enseignant_id' => ['sometimes', 'integer', 'exists:users,id'],
            'date_prevue' => ['sometimes', 'date_format:Y-m-d'],
            'statut' => ['sometimes', Rule::in(['programme', 'realise', 'annule'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);
    }
}
