<?php

namespace App\Http\Controllers\Api\V1\Administration;

use App\Http\Controllers\Controller;
use App\Models\FeuilleNotes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotesTransmisesController extends Controller
{
    private function feuilles()
    {
        return FeuilleNotes::query()->where('statut', 'transmise')
            ->with(['anneeAcademique', 'promotion', 'matiere', 'cours.module.matiere', 'dernierModificateur:id,nom,prenoms']);
    }

    private function presenter(FeuilleNotes $feuille): array
    {
        return [
            ...$feuille->only(['id', 'id_annee_academique', 'id_promotion', 'id_matiere', 'id_cours', 'statut', 'date_transmission']),
            'annee_academique' => $feuille->anneeAcademique?->only(['id', 'libelle']),
            'promotion' => $feuille->promotion?->only(['id', 'code', 'num_promotion']),
            'matiere' => ($feuille->matiere ?? $feuille->cours?->module?->matiere)?->only(['id', 'code', 'libelle']),
            'cours' => $feuille->cours?->only(['id', 'code', 'libelle']),
            'derniere_modification_par' => $feuille->dernierModificateur?->only(['id', 'nom', 'prenoms']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_annee_academique' => ['sometimes', 'integer', 'exists:annees_academiques,id'],
            'id_promotion' => ['sometimes', 'integer', 'exists:promotions,id'],
            'id_matiere' => ['sometimes', 'integer', 'exists:matieres,id'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $query = $this->feuilles()->withCount('notes');
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
            'feuilles_notes' => $items->getCollection()->map(fn ($f) => [...$this->presenter($f), 'nombre_notes' => $f->notes_count]),
            'meta' => ['current_page' => $items->currentPage(), 'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(), 'total' => $items->total(), 'from' => $items->firstItem(), 'to' => $items->lastItem()],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $feuille = $this->feuilles()->with('notes.etudiant:id,matricule,nom,prenoms')->findOrFail($id);

        return response()->json(['feuille_notes' => [
            ...$this->presenter($feuille),
            'notes' => $feuille->notes->map(fn ($note) => [
                ...$note->only(['id', 'id_etudiant', 'note']),
                'etudiant' => $note->etudiant?->only(['id', 'matricule', 'nom', 'prenoms']),
            ]),
        ]]);
    }
}
