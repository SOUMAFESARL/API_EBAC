<?php

namespace App\Http\Controllers\Api\V1\Administration;

use App\Http\Controllers\Controller;
use App\Models\FeuilleNotes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class NotesTransmisesController extends Controller
{
    private function feuilles()
    {
        return FeuilleNotes::query()->whereIn('statut', FeuilleNotes::STATUTS_TRANSMIS)
            ->with(['anneeAcademique', 'promotion', 'matiere', 'cours.module.matiere', 'enseignant:id,nom,prenoms', 'dernierModificateur:id,nom,prenoms']);
    }

    private function presenter(FeuilleNotes $feuille): array
    {
        return [
            ...$feuille->only(['id', 'id_annee_academique', 'id_promotion', 'id_matiere', 'id_cours', 'statut', 'date_transmission']),
            'annee_academique' => $feuille->anneeAcademique?->only(['id', 'libelle']),
            'promotion' => $feuille->promotion?->only(['id', 'code', 'num_promotion']),
            'matiere' => ($feuille->matiere ?? $feuille->cours?->module?->matiere)?->only(['id', 'code', 'libelle']),
            'cours' => $feuille->cours?->only(['id', 'code', 'libelle']),
            'enseignant' => $feuille->enseignant?->only(['id', 'nom', 'prenoms']),
            'derniere_modification_par' => $feuille->dernierModificateur?->only(['id', 'nom', 'prenoms']),
            'historique' => $feuille->relationLoaded('historique') ? $feuille->historique : null,
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_annee_academique' => ['sometimes', 'integer', 'exists:annees_academiques,id'],
            'id_promotion' => ['sometimes', 'integer', 'exists:promotions,id'],
            'id_matiere' => ['sometimes', 'integer', 'exists:matieres,id'],
            'statut' => ['sometimes', Rule::in(FeuilleNotes::STATUTS_TRANSMIS)],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $query = $this->feuilles()->withCount('notes')->with(['notes' => fn ($q) => $q->orderBy('id'), 'notes.etudiant:id,matricule,nom,prenoms']);
        if (isset($data['statut'])) {
            $query->where('statut', $data['statut']);
        }
        foreach (['id_annee_academique', 'id_promotion'] as $champ) {
            if (isset($data[$champ])) {
                $query->where($champ, $data[$champ]);
            }
        }
        if (isset($data['id_matiere'])) {
            $query->where(fn ($q) => $q->where('id_matiere', $data['id_matiere'])
                ->orWhereHas('cours.module', fn ($m) => $m->where('id_matiere', $data['id_matiere'])));
        }
        $items = $query->orderByDesc('date_transmission')->orderByDesc('id')->paginate($data['per_page'] ?? 15);

        return response()->json([
            'feuilles_notes' => $items->getCollection()->map(fn ($f) => [
                ...$this->presenter($f),
                'nombre_notes' => $f->notes_count,
                'notes' => $this->presenterNotes($f),
            ])->values(),
            'meta' => ['current_page' => $items->currentPage(), 'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(), 'total' => $items->total(), 'from' => $items->firstItem(), 'to' => $items->lastItem()],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $feuille = $this->feuilles()->with(['notes.etudiant:id,matricule,nom,prenoms', 'historique.acteur:id,nom,prenoms'])->findOrFail($id);

        return response()->json(['feuille_notes' => [
            ...$this->presenter($feuille),
            'notes' => $this->presenterNotes($feuille),
        ]]);
    }

    private function presenterNotes(FeuilleNotes $feuille): array
    {
        return $feuille->notes->map(fn ($note) => [
            ...$note->only(['id', 'id_etudiant', 'note']),
            'etudiant' => $note->etudiant?->only(['id', 'matricule', 'nom', 'prenoms']),
        ])->values()->all();
    }

    public function validerSecretariat(Request $request, int $id): JsonResponse
    {
        return $this->transition($request, $id, ['transmise', 'rejetee_direction'], 'validee_secretariat', 'validation_secretariat');
    }

    public function rejeterSecretariat(Request $request, int $id): JsonResponse
    {
        return $this->transition($request, $id, ['transmise', 'validee_secretariat', 'rejetee_direction'], 'rejetee_secretariat', 'rejet_secretariat', true);
    }

    public function transmettreDirection(Request $request, int $id): JsonResponse
    {
        return $this->transition($request, $id, ['validee_secretariat'], 'transmise_direction', 'transmission_direction');
    }

    public function validerDirection(Request $request, int $id): JsonResponse
    {
        return $this->transition($request, $id, ['transmise_direction'], 'validee_direction', 'validation_direction');
    }

    public function rejeterDirection(Request $request, int $id): JsonResponse
    {
        return $this->transition($request, $id, ['transmise_direction'], 'rejetee_direction', 'rejet_direction', true);
    }

    private function transition(Request $request, int $id, array $attendus, string $statut, string $action, bool $rejet = false): JsonResponse
    {
        $data = $request->validate(['motif' => [$rejet ? 'required' : 'sometimes', 'string', 'max:5000']]);
        $feuille = DB::transaction(function () use ($request, $id, $attendus, $statut, $action, $data) {
            $feuille = FeuilleNotes::whereKey($id)->lockForUpdate()->firstOrFail();
            if (! in_array($feuille->statut, $attendus, true)) {
                throw ValidationException::withMessages(['statut' => 'Cette action est impossible depuis le statut actuel de la feuille.']);
            }
            if ($feuille->notes()->whereHas('corrections', fn ($q) => $q->whereIn('statut', ['en_attente', 'autorisee']))->exists()) {
                throw ValidationException::withMessages(['notes' => 'Une correction est en cours. Traitez-la avant de poursuivre le circuit de validation.']);
            }
            $feuille->changerStatut($statut, $request->user()->id, $action, $data['motif'] ?? null);

            return $feuille;
        });
        $feuille->load(['anneeAcademique', 'promotion', 'matiere', 'cours.module.matiere', 'dernierModificateur:id,nom,prenoms', 'historique.acteur:id,nom,prenoms']);

        return response()->json(['message' => 'Statut de la feuille mis à jour.', 'feuille_notes' => $this->presenter($feuille)]);
    }
}
