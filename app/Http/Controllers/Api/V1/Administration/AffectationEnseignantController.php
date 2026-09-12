<?php

namespace App\Http\Controllers\Api\V1\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Administration\CreerAffectationEnseignantRequest;
use App\Http\Requests\Api\V1\Administration\TerminerAffectationEnseignantRequest;
use App\Models\AffectationEnseignant;
use App\Models\AnneeAcademique;
use App\Models\Cours;
use App\Models\Matiere;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AffectationEnseignantController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_annee_academique' => ['required', 'integer', 'exists:annees_academiques,id'],
            'statut' => ['sometimes', Rule::in(['en_cours', 'terminee'])], 'recherche' => ['nullable', 'string', 'max:180'],
            'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $today = now()->toDateString();
        $query = AffectationEnseignant::query()->where('id_annee_academique', $data['id_annee_academique'])
            ->with($this->relations())
            ->when(($data['statut'] ?? null) === 'en_cours', fn ($q) => $this->actives($q, $today))
            ->when(($data['statut'] ?? null) === 'terminee', fn ($q) => $q->whereNotNull('date_fin')->where('date_fin', '<', $today))
            ->when(! empty($data['recherche']), function ($q) use ($data) {
                $term = '%'.$data['recherche'].'%';
                $q->where(fn ($s) => $s->whereHas('enseignant', fn ($u) => $u->where('nom', 'like', $term)->orWhere('prenoms', 'like', $term))
                    ->orWhereHas('matiere', fn ($m) => $m->where('code', 'like', $term)->orWhere('libelle', 'like', $term))
                    ->orWhereHas('cours', fn ($c) => $c->where('code', 'like', $term)->orWhere('libelle', 'like', $term)));
            })->orderByDesc('date_debut')->orderByDesc('id');
        $items = $query->paginate($data['per_page'] ?? 15);

        return response()->json(['affectations' => $items->getCollection()->map(fn ($item) => $this->presenter($item))->values(),
            'meta' => ['current_page' => $items->currentPage(), 'last_page' => $items->lastPage(), 'per_page' => $items->perPage(), 'total' => $items->total(), 'from' => $items->firstItem(), 'to' => $items->lastItem()]]);
    }

    public function tableauDeBord(Request $request): JsonResponse
    {
        $data = $request->validate(['id_annee_academique' => ['required', 'integer', 'exists:annees_academiques,id']]);
        $year = $data['id_annee_academique'];
        $today = now()->toDateString();
        $active = AffectationEnseignant::query()->where('id_annee_academique', $year);
        $this->actives($active, $today);
        $teachers = $this->enseignantsEligibles();
        $mobilises = (clone $active)->distinct()->count('enseignant_id');
        $coveredSubjects = (clone $active)->where('portee', 'matiere')->pluck('id_matiere');
        $coveredCourses = (clone $active)->where('portee', 'cours')->pluck('id_cours');
        $sansEnseignant = Cours::query()->where('actif', true)->whereNotIn('id', $coveredCourses)
            ->whereHas('module', fn ($m) => $m->whereNotIn('id_matiere', $coveredSubjects))->count();

        return response()->json(['indicateurs' => ['enseignements_confies' => (clone $active)->count(), 'enseignants_mobilises' => $mobilises,
            'cours_sans_enseignant' => $sansEnseignant, 'enseignants_sans_charge' => max(0, (clone $teachers)->count() - $mobilises)]]);
    }

    public function options(Request $request): JsonResponse
    {
        $data = $request->validate(['id_annee_academique' => ['required', 'integer', 'exists:annees_academiques,id'], 'id_matiere' => ['sometimes', 'integer', 'exists:matieres,id']]);
        $eligibles = $this->enseignantsEligibles()->orderBy('nom')->orderBy('prenoms')->get(['id', 'code', 'nom', 'prenoms']);
        return response()->json(['enseignants' => $eligibles, 'enseignants_exclus' => User::whereHas('role', fn ($q) => $q->where('code', 'ENSEIGNANT'))->count() - $eligibles->count(),
            'matieres' => Matiere::with('niveau:id,code,libelle')->where('active', true)->orderBy('libelle')->get(['id', 'code', 'libelle', 'id_niveau', 'volume_horaire']),
            'cours' => Cours::with('module:id,id_matiere,libelle')->where('actif', true)->when(isset($data['id_matiere']), fn ($q) => $q->whereHas('module', fn ($m) => $m->where('id_matiere', $data['id_matiere'])))->orderBy('libelle')->get(['id', 'id_module', 'code', 'libelle', 'volume_horaire'])]);
    }

    public function store(CreerAffectationEnseignantRequest $request): JsonResponse
    {
        $data = $request->validated();
        $assignment = DB::transaction(function () use ($data, $request) {
            $year = AnneeAcademique::query()->lockForUpdate()->findOrFail($data['id_annee_academique']);
            $teacher = User::query()->with('role')->findOrFail($data['enseignant_id']);
            if (! $teacher->is_active || $teacher->statut !== 'Actif') throw ValidationException::withMessages(['enseignant_id' => ['Sélectionnez un enseignant actif.']]);
            if ($data['date_debut'] < $year->date_debut->toDateString() || $data['date_debut'] > $year->date_fin->toDateString()) throw ValidationException::withMessages(['date_debut' => ['La prise d’effet doit appartenir à l’année académique.']]);
            if ($data['portee'] === 'cours' && Cours::whereKey($data['id_cours'])->whereHas('module', fn ($q) => $q->where('id_matiere', $data['id_matiere']))->doesntExist()) throw ValidationException::withMessages(['id_cours' => ['Le cours doit appartenir à la matière sélectionnée.']]);
            $conflict = AffectationEnseignant::where('id_annee_academique', $year->id)->where('id_matiere', $data['id_matiere'])->where(fn ($q) => $q->whereNull('date_fin')->orWhere('date_fin', '>', $data['date_debut']))
                ->when($data['portee'] === 'cours', fn ($q) => $q->where(fn ($s) => $s->where('portee', 'matiere')->orWhere('id_cours', $data['id_cours'])))->lockForUpdate()->exists();
            if ($conflict) throw ValidationException::withMessages(['id_matiere' => ['Cet enseignement possède déjà une affectation sur cette période.']]);
            $assignment = AffectationEnseignant::create([...$data, 'id_cours' => $data['portee'] === 'matiere' ? null : $data['id_cours'], 'created_by' => $request->user()->id]);
            if ($data['portee'] === 'matiere') Matiere::whereKey($data['id_matiere'])->update(['enseignant_id' => $data['enseignant_id'], 'updated_by' => $request->user()->id]);
            return $assignment;
        });
        return response()->json(['message' => 'Enseignement confié avec succès.', 'affectation' => $this->presenter($assignment->load($this->relations()))], 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['affectation' => $this->presenter(AffectationEnseignant::with($this->relations())->findOrFail($id))]);
    }

    public function terminer(TerminerAffectationEnseignantRequest $request, int $id): JsonResponse
    {
        $assignment = DB::transaction(function () use ($request, $id) {
            $item = AffectationEnseignant::query()->with('anneeAcademique')->lockForUpdate()->findOrFail($id);
            if ($item->date_fin !== null) throw ValidationException::withMessages(['date_fin' => ['Cette affectation est déjà terminée.']]);
            $end = $request->validated('date_fin');
            if ($end < $item->date_debut->toDateString() || $end > $item->anneeAcademique->date_fin->toDateString()) throw ValidationException::withMessages(['date_fin' => ['La fin doit être comprise entre la prise d’effet et la fin de l’année académique.']]);
            $lastGrade = DB::table('lignes_bulletins')->join('bulletins', 'bulletins.id', '=', 'lignes_bulletins.id_bulletin')->join('inscriptions', 'inscriptions.id', '=', 'bulletins.id_inscription')
                ->where('lignes_bulletins.id_matiere', $item->id_matiere)->where('inscriptions.id_annee_academique', $item->id_annee_academique)->whereNotNull('lignes_bulletins.note')->max('lignes_bulletins.updated_at');
            if ($lastGrade && $end < substr($lastGrade, 0, 10)) throw ValidationException::withMessages(['date_fin' => ['La date de fin ne peut pas être antérieure aux notes déjà saisies.']]);
            $item->update(['date_fin' => $end, 'motif_fin' => $request->validated('motif'), 'updated_by' => $request->user()->id]);
            if ($item->portee === 'matiere') Matiere::whereKey($item->id_matiere)->where('enseignant_id', $item->enseignant_id)->update(['enseignant_id' => null, 'updated_by' => $request->user()->id]);
            return $item;
        });
        return response()->json(['message' => 'Affectation terminée avec succès.', 'affectation' => $this->presenter($assignment->load($this->relations()))]);
    }

    private function actives(Builder $query, string $date): Builder { return $query->where('date_debut', '<=', $date)->where(fn ($q) => $q->whereNull('date_fin')->orWhere('date_fin', '>', $date)); }
    private function enseignantsEligibles(): Builder { return User::query()->where('is_active', true)->where('statut', 'Actif')->whereHas('role', fn ($q) => $q->where('code', 'ENSEIGNANT')); }
    private function relations(): array { return ['anneeAcademique:id,libelle,date_debut,date_fin', 'enseignant:id,code,nom,prenoms,email', 'matiere.niveau:id,code,libelle', 'cours.module:id,id_matiere,libelle']; }
    private function presenter(AffectationEnseignant $item): array
    {
        $today = now()->toDateString();
        return [...$item->toArray(), 'statut' => $item->date_debut->toDateString() <= $today && ($item->date_fin === null || $item->date_fin->toDateString() > $today) ? 'en_cours' : 'terminee',
            'volume_horaire' => $item->portee === 'cours' ? $item->cours?->volume_horaire : $item->matiere?->volume_horaire];
    }
}
