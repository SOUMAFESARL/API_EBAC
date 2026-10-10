<?php

namespace App\Http\Controllers\Api\V1\Administration;

use App\Http\Controllers\Controller;
use App\Models\CoursAFaire;
use App\Models\Presence;
use App\Models\ProgrammationRattrapage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RattrapageController extends Controller
{
    private const RELATIONS = ['coursAFaire.etudiant', 'coursAFaire.matiere', 'coursAFaire.cours',
        'coursAFaire.seance', 'enseignant:id,nom,prenoms', 'salle'];

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
        $data = $this->payload($request);
        $item = DB::transaction(function () use ($request, $data) {
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
        $data = $this->payload($request, $item);
        $item = DB::transaction(function () use ($request, $item, $data) {
            $this->verifierAbsence($item->id_cours_a_faire);
            $item = ProgrammationRattrapage::whereKey($item->id)->lockForUpdate()->firstOrFail();
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

        return $data;
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
