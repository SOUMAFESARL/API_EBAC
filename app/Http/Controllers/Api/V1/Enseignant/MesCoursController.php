<?php

namespace App\Http\Controllers\Api\V1\Enseignant;

use App\Http\Controllers\Controller;
use App\Models\AffectationEnseignant;
use App\Models\AnneeAcademique;
use App\Models\Cours;
use App\Models\Creneau;
use App\Models\Matiere;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class MesCoursController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filtres = $request->validate([
            'id_annee_academique' => ['sometimes', 'integer', Rule::exists('annees_academiques', 'id')->whereNull('deleted_at')],
            'recherche' => ['nullable', 'string', 'max:180'],
            'id_niveau' => ['sometimes', 'integer', Rule::exists('niveaux', 'id')->whereNull('deleted_at')],
            'id_promotion' => ['sometimes', 'integer', Rule::exists('promotions', 'id')->whereNull('deleted_at')],
            'id_module_calendrier' => ['sometimes', 'integer', 'exists:modules_calendrier,id'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $annee = $this->annee($filtres['id_annee_academique'] ?? null);
        $query = $this->affectations($request)
            ->when($filtres['recherche'] ?? null, function ($q, string $recherche) {
                $terme = '%'.$recherche.'%';
                $q->where(fn ($s) => $s->whereHas('matiere', fn ($m) => $m->where('code', 'like', $terme)->orWhere('libelle', 'like', $terme))
                    ->orWhereHas('cours', fn ($c) => $c->where('code', 'like', $terme)->orWhere('libelle', 'like', $terme)));
            })
            ->when($filtres['id_niveau'] ?? null, fn ($q, int $id) => $q->whereHas('matiere', fn ($m) => $m->where('id_niveau', $id)))
            ->when($filtres['id_promotion'] ?? null, fn ($q, int $id) => $q->whereHas('matiere', fn ($m) => $m->whereHas('modules.cours.creneaux', fn ($c) => $c->where('id_promotion', $id))))
            ->when($filtres['id_module_calendrier'] ?? null, fn ($q, int $id) => $q->whereHas('matiere', fn ($m) => $m->whereHas('modules.cours.creneaux', fn ($c) => $c->where('id_module_calendrier', $id))))
            ->orderByDesc('date_debut')->get();
        $ids = $query->pluck('id_matiere')->unique()->values();
        $page = $filtres['page'] ?? 1;
        $parPage = $filtres['per_page'] ?? 15;

        return response()->json([
            'annee_academique' => $annee?->only(['id', 'libelle', 'date_debut', 'date_fin']),
            'matieres' => $ids->forPage($page, $parPage)->map(fn ($id) => $this->matiere($query->where('id_matiere', $id), $annee, false))->values(),
            'meta' => $this->meta($ids->count(), $page, $parPage),
        ]);
    }

    public function show(Request $request, int $matiere): JsonResponse
    {
        $data = $request->validate(['id_annee_academique' => ['sometimes', 'integer', Rule::exists('annees_academiques', 'id')->whereNull('deleted_at')]]);
        $annee = $this->annee($data['id_annee_academique'] ?? null);
        $affectations = $this->affectations($request)->where('id_matiere', $matiere)->get();
        abort_if($affectations->isEmpty(), 404, 'Cette matière ne vous est pas affectée.');

        return response()->json(['matiere' => $this->matiere($affectations, $annee, true)]);
    }

    private function affectations(Request $request)
    {
        return AffectationEnseignant::query()->whereHas('matiere')->where('enseignant_id', $request->user()->id)
            ->where('date_debut', '<=', now()->toDateString())->where(fn ($q) => $q->whereNull('date_fin')->orWhere('date_fin', '>', now()->toDateString()));
    }

    private function annee(?int $id): ?AnneeAcademique
    {
        if ($id) {
            return AnneeAcademique::find($id);
        }
        $date = now()->toDateString();

        return AnneeAcademique::where('active', true)->first()
            ?? AnneeAcademique::whereDate('date_debut', '<=', $date)->whereDate('date_fin', '>=', $date)->first();
    }

    private function matiere(Collection $affectations, ?AnneeAcademique $annee, bool $detail): array
    {
        $matiere = Matiere::with(['niveau', 'modules' => fn ($q) => $q->orderBy('ordre'), 'modules.cours' => fn ($q) => $q->where('actif', true)->orderBy('ordre')])->findOrFail($affectations->first()->id_matiere);
        $tous = $affectations->contains('portee', 'matiere');
        $idsCours = $affectations->pluck('id_cours')->filter()->all();
        $cours = $matiere->modules->flatMap->cours->filter(fn (Cours $c) => $tous || in_array($c->id, $idsCours, true));
        $creneaux = Creneau::with(['moduleCalendrier.calendrier', 'promotion.inscriptions'])
            ->where('enseignant_id', $affectations->first()->enseignant_id)->where('id_matiere', $matiere->id)
            ->where(fn ($q) => $q->whereNull('id_cours')->orWhereIn('id_cours', $cours->pluck('id')))
            ->whereHas('moduleCalendrier.calendrier', fn ($q) => $q->where('id_annee_academique', $annee?->id))->get();
        $promotions = $creneaux->pluck('promotion')->filter()->unique('id')->values();
        $base = [
            ...$matiere->only(['id', 'code', 'libelle']), 'niveau' => $matiere->niveau?->only(['id', 'code', 'libelle']),
            'promotions' => $promotions->map(fn ($p) => [...$p->only(['id', 'code', 'num_promotion']), 'nombre_etudiants' => $p->inscriptions->where('id_annee_academique', $annee?->id)->unique('id_etudiant')->count()])->all(),
            'modules_academiques' => $creneaux->pluck('moduleCalendrier')->filter()->unique('id')->sortBy('ordre')->values()->map->only(['id', 'libelle', 'ordre'])->all(),
            'nombre_etudiants' => $promotions->flatMap->inscriptions->where('id_annee_academique', $annee?->id)->unique('id_etudiant')->count(),
            'nombre_modules' => $cours->pluck('id_module')->unique()->count(), 'nombre_cours' => $cours->count(),
            'volume_horaire' => (float) $cours->sum('volume_horaire'), ...$this->progression($creneaux, $affectations, $annee),
        ];
        if (! $detail) {
            return $base;
        }

        return [...$base, 'modules' => $matiere->modules->map(function ($module) use ($cours, $creneaux, $affectations, $annee) {
            $items = $cours->where('id_module', $module->id)->values();
            if ($items->isEmpty()) {
                return null;
            }

            return [...$module->only(['id', 'code', 'libelle']), 'nombre_cours' => $items->count(), 'cours' => $items->map(fn ($item) => [
                ...$item->only(['id', 'code', 'libelle', 'ordre']), 'coefficient' => (float) $item->coefficient, 'volume_horaire' => (float) $item->volume_horaire,
                'nombre_etudiants' => $creneaux->where('id_cours', $item->id)->pluck('promotion')->filter()->flatMap->inscriptions->where('id_annee_academique', $annee?->id)->unique('id_etudiant')->count(),
                ...$this->progression($creneaux->filter(fn ($c) => $c->id_cours === null || $c->id_cours === $item->id), $affectations, $annee),
            ])->all()];
        })->filter()->values()];
    }

    private function progression(Collection $creneaux, Collection $affectations, ?AnneeAcademique $annee): array
    {
        $total = 0;
        $faites = 0;
        $today = CarbonImmutable::today();
        foreach ($creneaux as $creneau) {
            if (! $creneau->moduleCalendrier) {
                continue;
            }
            $debut = CarbonImmutable::parse($creneau->moduleCalendrier->date_debut)->max(CarbonImmutable::parse($annee->date_debut), CarbonImmutable::parse($affectations->min('date_debut')));
            $fin = CarbonImmutable::parse($creneau->moduleCalendrier->date_fin)->min(CarbonImmutable::parse($annee->date_fin));
            for ($date = $debut; $date->lte($fin); $date = $date->addDay()) {
                if ($date->dayOfWeekIso === $creneau->jour) {
                    $total++;
                    if ($date->lt($today)) {
                        $faites++;
                    }
                }
            }
        }

        return ['statut' => $total === 0 || $faites === 0 ? 'non_commence' : ($faites >= $total ? 'termine' : 'en_cours'),
            'seances_effectuees' => $faites, 'seances_planifiees' => $total, 'progression_pourcentage' => $total ? round($faites * 100 / $total, 1) : 0.0];
    }

    private function meta(int $total, int $page, int $parPage): array
    {
        return ['current_page' => $page, 'last_page' => max(1, (int) ceil($total / $parPage)), 'per_page' => $parPage, 'total' => $total,
            'from' => $total ? (($page - 1) * $parPage) + 1 : null, 'to' => $total ? min($page * $parPage, $total) : null];
    }
}
