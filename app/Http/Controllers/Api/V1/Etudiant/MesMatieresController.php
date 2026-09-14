<?php

namespace App\Http\Controllers\Api\V1\Etudiant;

use App\Http\Controllers\Controller;
use App\Models\Etudiant;
use App\Models\LigneBulletin;
use App\Models\Matiere;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class MesMatieresController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filtres = $request->validate([
            'recherche' => ['nullable', 'string', 'max:180'],
            'statut' => ['sometimes', Rule::in(['validee', 'a_completer', 'non_validee'])],
            'id_niveau' => ['sometimes', 'integer', Rule::exists('niveaux', 'id')->whereNull('deleted_at')],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        [$etudiant, $inscription] = $this->contexte($request);
        $niveau = $inscription->promotion->niveau;
        $notes = $this->notes($etudiant->id);
        $query = $this->matieresAutorisees($niveau->rang)
            ->when($filtres['recherche'] ?? null, function (Builder $query, string $recherche) {
                $terme = '%'.$recherche.'%';
                $query->where(fn ($q) => $q->where('code', 'like', $terme)->orWhere('libelle', 'like', $terme));
            })
            ->when($filtres['id_niveau'] ?? null, fn ($q, int $id) => $q->where('id_niveau', $id));
        $matieres = $query->get()->map(fn (Matiere $matiere) => $this->presenter($matiere, $notes->get($matiere->id)));
        if (isset($filtres['statut'])) {
            $matieres = $matieres->where('statut', $filtres['statut'])->values();
        }
        $page = $filtres['page'] ?? 1;
        $parPage = $filtres['per_page'] ?? 15;
        $total = $matieres->count();

        return response()->json([
            'etudiant' => $etudiant->only(['id', 'matricule', 'nom', 'prenoms']),
            'promotion' => $inscription->promotion->only(['id', 'code', 'num_promotion', 'annee_entree']),
            'niveau_actuel' => $niveau->only(['id', 'code', 'libelle', 'rang']),
            'regle_acces' => 'niveaux_jusqu_au_niveau_actuel',
            'matieres' => $matieres->forPage($page, $parPage)->values(),
            'meta' => $this->meta($total, $page, $parPage),
        ]);
    }

    public function show(Request $request, int $matiere): JsonResponse
    {
        [$etudiant, $inscription] = $this->contexte($request);
        $item = $this->matieresAutorisees($inscription->promotion->niveau->rang)
            ->with(['modules' => fn ($q) => $q->orderBy('ordre'), 'modules.cours' => fn ($q) => $q->where('actif', true)->orderBy('ordre')])
            ->findOrFail($matiere);
        $note = $this->notes($etudiant->id)->get($item->id);
        $modules = $item->modules->map(fn ($module) => [
            ...$module->only(['id', 'code', 'libelle', 'ordre']),
            'cours' => $module->cours->map(fn ($cours) => [
                ...$cours->only(['id', 'code', 'libelle', 'ordre']),
                'coefficient' => (float) $cours->coefficient,
                'volume_horaire' => (float) $cours->volume_horaire,
                'evaluation' => null, 'note' => null,
            ])->values(),
        ])->values();

        return response()->json(['matiere' => [
            ...$this->presenter($item, $note),
            'nombre_modules' => $modules->count(),
            'nombre_cours' => $modules->sum(fn ($module) => $module['cours']->count()),
            'modules' => $modules,
        ]]);
    }

    private function contexte(Request $request): array
    {
        $etudiant = Etudiant::query()->where('user_id', $request->user()->id)->firstOrFail();
        $inscription = $etudiant->inscriptions()->with(['promotion.niveau', 'anneeAcademique'])
            ->latest('date_inscription')->latest('id')->firstOrFail();

        return [$etudiant, $inscription];
    }

    private function matieresAutorisees(int $rang): Builder
    {
        return Matiere::query()->where('active', true)->whereHas('niveau', fn ($q) => $q->where('rang', '<=', $rang))
            ->with(['niveau', 'enseignant:id,nom,prenoms'])->orderBy('id_niveau')->orderBy('libelle');
    }

    private function notes(int $etudiantId): Collection
    {
        return LigneBulletin::query()
            ->whereHas('bulletin', fn ($q) => $q->whereNotNull('date_publication')->whereHas('inscription', fn ($i) => $i->where('id_etudiant', $etudiantId)))
            ->with('bulletin:id,id_inscription,periode,date_publication,statut')->get()
            ->sortByDesc(fn ($ligne) => $ligne->bulletin->date_publication)->unique('id_matiere')->keyBy('id_matiere');
    }

    private function presenter(Matiere $matiere, ?LigneBulletin $ligne): array
    {
        $note = $ligne?->note !== null ? (float) $ligne->note : null;
        $statut = $note === null ? 'a_completer' : ($note >= (float) $matiere->note_validation ? 'validee' : 'non_validee');

        return [
            ...$matiere->only(['id', 'code', 'libelle']),
            'niveau' => $matiere->niveau?->only(['id', 'code', 'libelle', 'rang']),
            'coefficient' => (float) $matiere->coefficient,
            'note_validation' => (float) $matiere->note_validation,
            'enseignant' => $matiere->enseignant?->only(['id', 'nom', 'prenoms']),
            'statut' => $statut, 'note' => $note,
            'periode_note' => $ligne?->bulletin?->periode,
            'date_publication_note' => $ligne?->bulletin?->date_publication,
        ];
    }

    private function meta(int $total, int $page, int $parPage): array
    {
        return ['current_page' => $page, 'last_page' => max(1, (int) ceil($total / $parPage)), 'per_page' => $parPage, 'total' => $total,
            'from' => $total ? (($page - 1) * $parPage) + 1 : null, 'to' => $total ? min($page * $parPage, $total) : null];
    }
}
