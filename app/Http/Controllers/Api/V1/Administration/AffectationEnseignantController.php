<?php

namespace App\Http\Controllers\Api\V1\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Administration\CreerAffectationEnseignantRequest;
use App\Http\Requests\Api\V1\Administration\TerminerAffectationEnseignantRequest;
use App\Models\AffectationEnseignant;
use App\Models\Cours;
use App\Models\Matiere;
use App\Models\User;
use App\Services\AffectationEnseignantService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AffectationEnseignantController extends Controller
{
    public function __construct(private AffectationEnseignantService $service) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'statut' => ['sometimes', Rule::in(['en_cours', 'terminee'])], 'recherche' => ['nullable', 'string', 'max:180'],
            'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $today = now()->toDateString();
        $query = AffectationEnseignant::query()
            ->with($this->relations())
            ->when(($data['statut'] ?? null) === 'en_cours', fn ($q) => $this->actives($q, $today))
            ->when(($data['statut'] ?? null) === 'terminee', fn ($q) => $q->whereNotNull('date_fin')->where('date_fin', '<=', $today))
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
        $today = now()->toDateString();
        $active = AffectationEnseignant::query();
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
        $data = $request->validate(['id_matiere' => ['sometimes', 'integer', 'exists:matieres,id']]);
        $eligibles = $this->enseignantsEligibles()->orderBy('nom')->orderBy('prenoms')->get(['id', 'code', 'nom', 'prenoms']);

        return response()->json(['enseignants' => $eligibles, 'enseignants_exclus' => User::whereHas('role', fn ($q) => $q->where('code', 'ENSEIGNANT'))->count() - $eligibles->count(),
            'matieres' => Matiere::with('niveau:id,code,libelle')->where('active', true)->orderBy('libelle')->get(['id', 'code', 'libelle', 'id_niveau', 'volume_horaire']),
            'cours' => Cours::with('module:id,id_matiere,libelle')->where('actif', true)->when(isset($data['id_matiere']), fn ($q) => $q->whereHas('module', fn ($m) => $m->where('id_matiere', $data['id_matiere'])))->orderBy('libelle')->get(['id', 'id_module', 'code', 'libelle', 'volume_horaire'])]);
    }

    public function store(CreerAffectationEnseignantRequest $request): JsonResponse
    {
        $assignment = DB::transaction(fn () => $this->service->creer($request->validated(), $request->user()->id));

        return response()->json(['message' => 'Enseignement confié avec succès.', 'affectation' => $this->presenter($assignment->load($this->relations()))], 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['affectation' => $this->presenter(AffectationEnseignant::with($this->relations())->findOrFail($id))]);
    }

    public function terminer(TerminerAffectationEnseignantRequest $request, int $id): JsonResponse
    {
        $assignment = DB::transaction(function () use ($request, $id) {
            $cible = AffectationEnseignant::findOrFail($id);
            Matiere::lockForUpdate()->findOrFail($cible->id_matiere);
            $item = AffectationEnseignant::lockForUpdate()->findOrFail($id);
            $this->service->terminer($item, $request->validated('date_fin'), $request->validated('motif'), $request->user()->id);

            return $item;
        });

        return response()->json(['message' => 'Affectation terminée avec succès.', 'affectation' => $this->presenter($assignment->load($this->relations()))]);
    }

    private function actives(Builder $query, string $date): Builder
    {
        return $query->where('date_debut', '<=', $date)->where(fn ($q) => $q->whereNull('date_fin')->orWhere('date_fin', '>', $date));
    }

    private function enseignantsEligibles(): Builder
    {
        return User::query()->where('is_active', true)->where('statut', 'Actif')->whereHas('role', fn ($q) => $q->where('code', 'ENSEIGNANT'));
    }

    private function relations(): array
    {
        return ['enseignant:id,code,nom,prenoms,email', 'matiere.niveau:id,code,libelle', 'cours.module:id,id_matiere,libelle'];
    }

    private function presenter(AffectationEnseignant $item): array
    {
        $today = now()->toDateString();

        return [...$item->toArray(), 'statut' => $item->date_debut->toDateString() <= $today && ($item->date_fin === null || $item->date_fin->toDateString() > $today) ? 'en_cours' : 'terminee',
            'volume_horaire' => $item->portee === 'cours' ? $item->cours?->volume_horaire : $item->matiere?->volume_horaire];
    }
}
