<?php

namespace App\Http\Controllers\Api\V1\Enseignant;

use App\Http\Controllers\Controller;
use App\Models\FeuilleNotes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransmissionNotesController extends Controller
{
    private function feuilles(Request $request)
    {
        return FeuilleNotes::query()->where('transmise_par', $request->user()->id)
            ->whereIn('statut', FeuilleNotes::STATUTS_TRANSMIS)
            ->with(['anneeAcademique', 'promotion', 'matiere', 'cours.module.matiere', 'historique.acteur:id,nom,prenoms'])
            ->withCount('notes');
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_seance' => ['sometimes', 'integer', 'exists:seances_cahier_texte,id'],
            'id_annee_academique' => ['sometimes', 'integer', 'exists:annees_academiques,id'],
            'id_promotion' => ['sometimes', 'integer', 'exists:promotions,id'],
            'id_matiere' => ['sometimes', 'integer', 'exists:matieres,id'],
            'id_cours' => ['sometimes', 'integer', 'exists:cours,id'],
            'statut' => ['sometimes', Rule::in(FeuilleNotes::STATUTS_TRANSMIS)],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);
        $query = $this->feuilles($request);
        foreach (['id_seance', 'id_annee_academique', 'id_promotion', 'id_cours', 'statut'] as $champ) {
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
            'transmissions' => $items->getCollection()->map(fn ($feuille) => $this->presenter($feuille)),
            'meta' => ['current_page' => $items->currentPage(), 'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(), 'total' => $items->total(), 'from' => $items->firstItem(), 'to' => $items->lastItem()],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $feuille = $this->feuilles($request)->findOrFail($id);

        return response()->json(['transmission' => $this->presenter($feuille)]);
    }

    private function presenter(FeuilleNotes $feuille): array
    {
        [$etape, $libelle, $message] = match ($feuille->statut) {
            'transmise' => [2, 'Contrôle secrétariat', 'Notes transmises au secrétariat, en cours de contrôle avant soumission à la direction.'],
            'validee_secretariat' => [3, 'Transmission direction', 'Notes validées par le secrétariat, en attente de transmission à la direction.'],
            'transmise_direction' => [3, 'Validation direction', 'Notes transmises à la direction, en attente de validation.'],
            'validee_direction' => [4, 'Validé et verrouillé', 'Notes validées par la direction et verrouillées.'],
            'rejetee_secretariat' => [1, 'Refus secrétariat', 'Notes refusées par le secrétariat. Corrigez la feuille puis transmettez-la à nouveau.'],
            'rejetee_direction' => [2, 'Rejet direction', 'Notes rejetées par la direction, en attente de réexamen par le secrétariat.'],
        };
        $rejet = in_array($feuille->statut, ['rejetee_secretariat', 'rejetee_direction'], true);
        $decision = $feuille->historique->last();
        $libelles = [1 => 'Saisie enseignant', 2 => 'Contrôle secrétariat', 3 => 'Transmission direction', 4 => 'Validé et verrouillé'];

        return [
            ...$feuille->only(['id', 'statut', 'date_transmission', 'transmise_par', 'id_annee_academique', 'id_promotion', 'id_matiere', 'id_cours', 'id_seance']),
            'annee_academique' => $feuille->anneeAcademique?->only(['id', 'libelle']),
            'promotion' => $feuille->promotion?->only(['id', 'code', 'num_promotion']),
            'matiere' => ($feuille->matiere ?? $feuille->cours?->module?->matiere)?->only(['id', 'code', 'libelle']),
            'cours' => $feuille->cours?->only(['id', 'code', 'libelle']),
            'nombre_notes' => $feuille->notes_count,
            'circuit_validation' => [
                'etape_actuelle' => $etape, 'libelle' => $libelle, 'message' => $message,
                'rejetee' => $rejet, 'motif_rejet' => $rejet ? $decision?->motif : null,
                'correction_enseignant_requise' => $feuille->statut === 'rejetee_secretariat',
                'validee_et_verrouillee' => $feuille->statut === 'validee_direction',
                'etapes' => collect($libelles)->map(fn ($nom, $numero) => [
                    'numero' => $numero, 'libelle' => $nom,
                    'statut' => $numero < $etape || ($numero === 4 && $etape === 4)
                        ? 'terminee' : ($numero === $etape ? ($rejet ? 'rejetee' : 'en_cours') : 'en_attente'),
                ])->values(),
            ],
            'derniere_decision' => $decision,
            'historique' => $feuille->historique,
        ];
    }
}
