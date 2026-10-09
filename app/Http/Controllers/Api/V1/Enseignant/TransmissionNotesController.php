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

    private function filtrer(Request $request): array
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
                if ($champ === 'statut' && $data[$champ] === 'transmise') {
                    $query->whereIn('statut', FeuilleNotes::STATUTS_TRANSMIS);
                } else {
                    $query->where($champ, $data[$champ]);
                }
            }
        }
        if (isset($data['id_matiere'])) {
            $query->where(fn ($q) => $q->where('id_matiere', $data['id_matiere'])
                ->orWhereHas('cours.module', fn ($m) => $m->where('id_matiere', $data['id_matiere'])));
        }
        return [$query->orderByDesc('date_transmission')->orderByDesc('id'), $data];
    }

    public function index(Request $request): JsonResponse
    {
        [$query, $data] = $this->filtrer($request);
        $items = $query->paginate($data['per_page'] ?? 15);

        return response()->json([
            'transmissions' => $items->getCollection()->map(fn ($feuille) => $this->presenter($feuille)),
            'meta' => ['current_page' => $items->currentPage(), 'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(), 'total' => $items->total(), 'from' => $items->firstItem(), 'to' => $items->lastItem()],
        ]);
    }

    private function notes(FeuilleNotes $feuille): array
    {
        return $feuille->notes->map(fn ($note) => [
            ...$note->only(['id', 'id_etudiant', 'evaluation', 'note']),
            'etudiant' => $note->etudiant?->only(['id', 'matricule', 'nom', 'prenoms']),
        ])->values()->all();
    }

    public function feuillesNotes(Request $request): JsonResponse
    {
        [$query] = $this->filtrer($request);
        $items = $query->with(['notes' => fn ($q) => $q->orderBy('id'), 'notes.etudiant:id,matricule,nom,prenoms'])->get();

        return response()->json(['feuilles_notes' => $items->map(fn ($feuille) => [
            ...$this->presenter($feuille), 'notes' => $this->notes($feuille),
        ])->values()]);
    }

    public function tableau(Request $request): JsonResponse
    {
        [$query] = $this->filtrer($request);
        $items = $query->with(['notes' => fn ($q) => $q->orderBy('id'), 'notes.etudiant:id,matricule,nom,prenoms'])->get();

        return response()->json($items->map(function ($feuille) {
            $contexte = $this->presenter($feuille);

            return [
                'id_feuille_notes' => $feuille->id,
                ...$feuille->only(['id_seance', 'id_cours', 'id_matiere', 'id_promotion', 'id_annee_academique', 'statut', 'date_transmission']),
                'statut' => $contexte['statut'], 'statut_workflow' => $contexte['statut_workflow'], 'motif' => $contexte['motif'],
                'cours' => $contexte['cours'], 'matiere' => $contexte['matiere'],
                'promotion' => $contexte['promotion'],
                'notes' => $this->notes($feuille),
            ];
        })->values());
    }

    public function tableauGeneral(Request $request): JsonResponse
    {
        [$query] = $this->filtrer($request);
        $feuilles = $query->with(['notes' => fn ($q) => $q->orderBy('id'),
            'notes.etudiant:id,matricule,nom,prenoms', 'seance'])->get();

        // Une promotion et une annee distinctes ne partagent jamais le meme tableau.
        $groupes = $feuilles->groupBy(fn ($f) => implode(':', [
            $f->id_matiere ?? $f->cours?->module?->id_matiere,
            $f->id_promotion, $f->id_annee_academique,
        ]));

        return response()->json(['tableaux' => $groupes->map(function ($groupe) {
            $premiere = $groupe->first();
            $colonnes = [];
            $lignes = [];
            $ordonnees = $groupe->sortBy(fn ($f) => [
                $f->seance?->date_effective?->format('Y-m-d')
                    ?? $f->seance?->date_prevue?->format('Y-m-d') ?? '', $f->id,
            ]);
            foreach ($ordonnees as $feuille) {
                foreach ($feuille->notes->groupBy('evaluation') as $evaluation => $notes) {
                    $cle = 'feuille_'.$feuille->id.'_'.md5((string) $evaluation);
                    $colonnes[] = [
                        'cle' => $cle, 'libelle' => (string) $evaluation,
                        'id_feuille_notes' => $feuille->id, 'id_seance' => $feuille->id_seance,
                        'id_cours' => $feuille->id_cours, 'evaluation' => $evaluation,
                        'date_seance' => $feuille->seance?->date_effective?->format('Y-m-d')
                            ?? $feuille->seance?->date_prevue?->format('Y-m-d'),
                    ];
                    foreach ($notes as $note) {
                        $id = $note->id_etudiant;
                        $lignes[$id] ??= ['id_etudiant' => $id,
                            'etudiant' => $note->etudiant?->only(['id', 'matricule', 'nom', 'prenoms']), 'notes' => []];
                        $lignes[$id]['notes'][$cle] = $note->note === null ? null : (float) $note->note;
                    }
                }
            }
            $vides = array_fill_keys(array_column($colonnes, 'cle'), null);
            $clesValidees = $groupe->where('statut', 'validee_direction')->pluck('id');
            $colonnesValidees = array_flip(collect($colonnes)->whereIn('id_feuille_notes', $clesValidees)->pluck('cle')->all());
            $lignes = collect($lignes)->map(function ($ligne) use ($vides, $colonnesValidees) {
                $ligne['notes'] = array_replace($vides, $ligne['notes']);
                $valeurs = array_filter(array_intersect_key($ligne['notes'], $colonnesValidees), fn ($note) => $note !== null);
                $ligne['moyenne'] = count($valeurs) ? round(array_sum($valeurs) / count($valeurs), 2) : null;

                return $ligne;
            })->sortBy(fn ($ligne) => [$ligne['etudiant']['nom'] ?? '',
                $ligne['etudiant']['prenoms'] ?? '', $ligne['id_etudiant']])->values();

            return [
                'id_matiere' => $premiere->id_matiere ?? $premiere->cours?->module?->id_matiere,
                'matiere' => ($premiere->matiere ?? $premiere->cours?->module?->matiere)?->only(['id', 'code', 'libelle']),
                'id_promotion' => $premiere->id_promotion, 'promotion' => $premiere->promotion,
                'id_annee_academique' => $premiere->id_annee_academique,
                'annee_academique' => $premiere->anneeAcademique?->only(['id', 'libelle']),
                'colonnes' => $colonnes, 'lignes' => $lignes,
            ];
        })->values()]);
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
            'rejetee_direction' => [1, 'Rejet direction', 'Notes rejetées par la direction, en attente de réexamen par le secrétariat.'],
        };
        $rejet = in_array($feuille->statut, ['rejetee_secretariat', 'rejetee_direction'], true);
        $decision = $feuille->historique->last();
        $libelles = [1 => 'Saisie enseignant', 2 => 'Contrôle secrétariat', 3 => 'Transmission direction', 4 => 'Validé et verrouillé'];

        return [
            ...$feuille->only(['id', 'statut', 'date_transmission', 'transmise_par', 'id_annee_academique', 'id_promotion', 'id_matiere', 'id_cours', 'id_seance']),
            'statut' => $feuille->statutPourRole('ENSEIGNANT'),
            'statut_workflow' => $feuille->statut,
            'motif' => $feuille->motifRejet(),
            'annee_academique' => $feuille->anneeAcademique?->only(['id', 'libelle']),
            'promotion' => $feuille->promotion?->only(['id', 'code', 'num_promotion']),
            'matiere' => ($feuille->matiere ?? $feuille->cours?->module?->matiere)?->only(['id', 'code', 'libelle']),
            'cours' => $feuille->cours?->only(['id', 'code', 'libelle']),
            'nombre_notes' => $feuille->notes_count,
            'circuit_validation' => [
                'etape_actuelle' => $etape, 'libelle' => $libelle, 'message' => $message,
                'rejetee' => $rejet, 'motif_rejet' => $rejet ? $decision?->motif : null,
                'correction_enseignant_requise' => in_array($feuille->statut, ['rejetee_secretariat', 'rejetee_direction'], true),
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
