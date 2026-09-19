<?php

namespace App\Http\Controllers\Api\V1\Enseignant;

use App\Http\Controllers\Controller;
use App\Models\AnneeAcademique;
use App\Models\CoursAFaire;
use App\Models\Etudiant;
use App\Models\FeuillePresence;
use App\Models\SeanceCahierTexte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ListePresenceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_matiere' => ['sometimes', 'integer', 'exists:matieres,id'],
            'id_cours' => ['sometimes', 'integer', 'exists:cours,id'],
            'id_promotion' => ['sometimes', 'integer', 'exists:promotions,id'],
            'date_debut' => ['sometimes', 'date'],
            'date_fin' => ['sometimes', 'date', 'after_or_equal:date_debut'],
        ]);

        $seances = $this->seances($request)
            ->when($data['id_matiere'] ?? null, fn ($q, $id) => $q->where('id_matiere', $id))
            ->when($data['id_cours'] ?? null, fn ($q, $id) => $q->where('id_cours', $id))
            ->when($data['id_promotion'] ?? null, fn ($q, $id) => $q->where('id_promotion', $id))
            ->when($data['date_debut'] ?? null, fn ($q, $date) => $q->whereDate('date_prevue', '>=', $date))
            ->when($data['date_fin'] ?? null, fn ($q, $date) => $q->whereDate('date_prevue', '<=', $date))
            ->with([
                'matiere',
                'cours.module',
                'promotion',
                'niveau',
                'salle',
                'moduleCalendrier.calendrier',
                'feuillePresence.presences.etudiant',
            ])
            ->orderByDesc('date_prevue')
            ->orderByDesc('heure_debut_prevue')
            ->get();

        // On précharge les étudiants concernés pour chaque séance sans requête N+1
        $this->prechargerEtudiants($seances);

        return response()->json([
            'seances' => $seances->map(fn ($seance) => $this->presenter($seance)),
            'nombre_seances' => $seances->count(),
        ]);
    }

    public function show(Request $request, int $seance): JsonResponse
    {
        $item = $this->seances($request)->with([
            'matiere',
            'cours.module',
            'promotion',
            'niveau',
            'salle',
            'moduleCalendrier.calendrier',
            'feuillePresence.presences.etudiant',
        ])->findOrFail($seance);

        return response()->json(['feuille_presence' => $this->presenter($item)]);
    }

    public function update(Request $request, int $seance): JsonResponse
    {
        $data = $this->validerPayload($request, true);
        $item = $this->seances($request)->with('moduleCalendrier.calendrier')->findOrFail($seance);
        $feuille = DB::transaction(fn () => $this->synchroniser($item, $data['presences'], $request->user()->id));

        return response()->json([
            'message' => 'Liste de présence enregistrée.',
            'feuille_presence' => $this->presenter($item->fresh()),
        ]);
    }

    public function valider(Request $request, int $seance): JsonResponse
    {
        $data = $this->validerPayload($request, false);
        $item = $this->seances($request)->with('moduleCalendrier.calendrier')->findOrFail($seance);
        if ($item->statut !== 'realisee') {
            throw ValidationException::withMessages(['seance' => ['Seule une séance réalisée peut recevoir une liste de présence définitive.']]);
        }
        DB::transaction(function () use ($item, $data, $request) {
            $feuille = isset($data['presences'])
                ? $this->synchroniser($item, $data['presences'], $request->user()->id)
                : FeuillePresence::with('presences')->where('id_seance', $item->id)->lockForUpdate()->first();
            if (! $feuille || $feuille->presences->isEmpty()) {
                throw ValidationException::withMessages(['presences' => ['Enregistrez les présences avant la validation.']]);
            }
            if ($feuille->statut === 'validee') {
                throw ValidationException::withMessages(['presences' => ['Cette liste de présence est déjà validée définitivement.']]);
            }
            $concernes = $this->etudiantsConcernes($item)->pluck('id')->sort()->values();
            if ($feuille->presences->pluck('id_etudiant')->sort()->values()->all() !== $concernes->all()) {
                throw ValidationException::withMessages(['presences' => ['Tous les étudiants concernés doivent avoir un statut de présence.']]);
            }
            $feuille->update([
                'statut' => 'validee',
                'date_validation' => now(),
                'validee_par' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
            foreach ($feuille->presences->where('statut', 'absent') as $presence) {
                CoursAFaire::firstOrCreate(
                    ['id_etudiant' => $presence->id_etudiant, 'id_seance' => $item->id],
                    ['id_cours' => $item->id_cours, 'id_matiere' => $item->id_matiere, 'statut' => 'a_faire', 'motif' => 'absence'],
                );
            }
        });

        return response()->json([
            'message' => 'Liste de présence validée définitivement.',
            'feuille_presence' => $this->presenter($item->fresh()),
        ]);
    }

    private function prechargerEtudiants(Collection $seances): void
    {
        if ($seances->isEmpty()) {
            return;
        }

        $activeAnneeId = AnneeAcademique::where('active', true)->value('id');
        $cache = [];

        foreach ($seances as $seance) {
            $anneeId = $seance->moduleCalendrier?->calendrier?->id_annee_academique ?? $activeAnneeId;
            $cle = "{$anneeId}_{$seance->id_promotion}_{$seance->id_niveau}";

            if (! isset($cache[$cle])) {
                $cache[$cle] = Etudiant::query()
                    ->whereHas('inscriptions', fn ($q) => $q->when(! $seance->id_promotion, fn ($i) => $i->where('id_annee_academique', $anneeId))
                        ->when($seance->id_promotion, fn ($i, $id) => $i->where('id_promotion', $id))
                        ->when(! $seance->id_promotion, fn ($i) => $i->whereHas('promotion', fn ($p) => $p->where('id_niveau', $seance->id_niveau))))
                    ->orderBy('nom')
                    ->orderBy('prenoms')
                    ->get(['id', 'matricule', 'nom', 'prenoms']);
            }

            $seance->setRelation('etudiantsConcernes', $cache[$cle]);
        }
    }

    private function synchroniser(SeanceCahierTexte $seance, array $presences, int $userId): FeuillePresence
    {
        $feuille = FeuillePresence::with('presences')->where('id_seance', $seance->id)->lockForUpdate()->firstOrCreate(
            ['id_seance' => $seance->id],
            ['statut' => 'brouillon', 'created_by' => $userId],
        );
        if ($feuille->statut === 'validee') {
            throw ValidationException::withMessages(['presences' => ['Cette liste est validée et ne peut plus être modifiée.']]);
        }
        $concernes = $this->etudiantsConcernes($seance)->pluck('id')->sort()->values();
        if ($concernes->isEmpty()) {
            throw ValidationException::withMessages(['presences' => ['Aucun étudiant n’est inscrit pour cette séance.']]);
        }
        $fournis = collect($presences)->pluck('id_etudiant')->sort()->values();
        if ($fournis->duplicates()->isNotEmpty() || $fournis->all() !== $concernes->all()) {
            throw ValidationException::withMessages(['presences' => ['La liste doit contenir exactement tous les étudiants concernés, sans doublon.']]);
        }
        foreach ($presences as $presence) {
            $feuille->presences()->updateOrCreate(
                ['id_etudiant' => $presence['id_etudiant']],
                ['statut' => $presence['statut']],
            );
        }
        $feuille->update(['updated_by' => $userId]);

        return $feuille->fresh('presences');
    }

    private function etudiantsConcernes(SeanceCahierTexte $seance): Collection
    {
        if ($seance->relationLoaded('etudiantsConcernes')) {
            return $seance->getRelation('etudiantsConcernes');
        }

        $anneeId = $seance->moduleCalendrier?->calendrier?->id_annee_academique
            ?? AnneeAcademique::where('active', true)->value('id');

        return Etudiant::query()->whereHas('inscriptions', fn ($q) => $q->when(! $seance->id_promotion, fn ($i) => $i->where('id_annee_academique', $anneeId))
            ->when($seance->id_promotion, fn ($i, $id) => $i->where('id_promotion', $id))
            ->when(! $seance->id_promotion, fn ($i) => $i->whereHas('promotion', fn ($p) => $p->where('id_niveau', $seance->id_niveau))))
            ->orderBy('nom')
            ->orderBy('prenoms')
            ->get(['id', 'matricule', 'nom', 'prenoms']);
    }

    private function validerPayload(Request $request, bool $required): array
    {
        return $request->validate([
            'presences' => [$required ? 'required' : 'sometimes', 'array', 'min:1'],
            'presences.*.id_etudiant' => ['required', 'integer'],
            'presences.*.statut' => ['required', Rule::in(['present', 'absent'])],
        ]);
    }

    private function seances(Request $request)
    {
        return SeanceCahierTexte::where('enseignant_id', $request->user()->id);
    }

    private function resume(SeanceCahierTexte $seance): array
    {
        return [
            ...$seance->only(['id', 'date_prevue', 'heure_debut_prevue', 'heure_fin_prevue', 'statut']),
            'matiere' => $seance->matiere?->only(['id', 'code', 'libelle']),
            'module' => $seance->cours?->module?->only(['id', 'code', 'libelle']),
            'cours' => $seance->cours?->only(['id', 'code', 'libelle']),
            'promotion' => $seance->promotion?->only(['id', 'code', 'num_promotion']),
            'presence' => [
                'statut' => $seance->feuillePresence?->statut ?? 'a_soumettre',
                'presents' => $seance->feuillePresence?->presences?->where('statut', 'present')->count() ?? 0,
                'absents' => $seance->feuillePresence?->presences?->where('statut', 'absent')->count() ?? 0,
            ],
        ];
    }

    private function presenter(SeanceCahierTexte $seance): array
    {
        $seance->loadMissing([
            'matiere',
            'cours.module',
            'promotion',
            'niveau',
            'salle',
            'moduleCalendrier.calendrier',
            'feuillePresence.presences.etudiant',
        ]);
        $concernes = $this->etudiantsConcernes($seance);
        $marques = $seance->feuillePresence?->presences?->keyBy('id_etudiant') ?? collect();

        return [
            ...$this->resume($seance),
            'etudiants' => $concernes->map(fn ($etudiant) => [
                ...$etudiant->only(['id', 'matricule', 'nom', 'prenoms']),
                'statut_presence' => $marques->get($etudiant->id)?->statut,
            ]),
            'modifiable' => $seance->feuillePresence?->statut !== 'validee',
            'date_validation' => $seance->feuillePresence?->date_validation,
        ];
    }
}
