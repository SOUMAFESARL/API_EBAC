<?php

namespace App\Http\Controllers\Api\V1\Administration;

use App\Http\Controllers\Controller;
use App\Models\AnneeAcademique;
use App\Models\Bulletin;
use App\Models\Niveau;
use App\Models\User;
use App\Services\DocumentBulletinService;
use App\Services\MentionBulletinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Registre des bulletins (09/11/2026) : l'administration consulte tous les
 * bulletins d'un niveau — annuels et de fin de cycle, quel que soit leur
 * statut — et ouvre chacun sur le même document que l'étudiant.
 *
 * Le niveau est celui **du bulletin** (`bulletins.id_niveau`), pas celui
 * qu'a aujourd'hui sa promotion : un bulletin de 1re Année y reste quand
 * la promotion avance.
 */
class RegistreBulletinsController extends Controller
{
    public function __construct(private DocumentBulletinService $document) {}

    /** Les niveaux (avec leur nombre de bulletins) et les années académiques. */
    public function filtres(): JsonResponse
    {
        $comptes = Bulletin::query()->selectRaw('id_niveau, count(*) as total')
            ->groupBy('id_niveau')->pluck('total', 'id_niveau');

        return response()->json([
            'niveaux' => Niveau::query()->orderBy('rang')->get()->map(fn (Niveau $n) => [
                ...$n->only(['id', 'code', 'libelle', 'rang']),
                'bulletins' => (int) ($comptes[$n->id] ?? 0),
            ])->values(),
            'annees_academiques' => AnneeAcademique::query()->orderByDesc('date_debut')->get()
                ->map(fn (AnneeAcademique $a) => $a->only(['id', 'libelle', 'date_debut', 'date_fin']))->values(),
        ]);
    }

    /** Tous les bulletins d'un niveau, d'une année si elle est donnée. */
    public function index(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'id_niveau' => ['required', 'integer', 'exists:niveaux,id'],
            'id_annee_academique' => ['sometimes', 'nullable', 'integer', 'exists:annees_academiques,id'],
        ]);

        $bulletins = Bulletin::query()
            ->with(['inscription.etudiant', 'inscription.promotion', 'inscription.anneeAcademique', 'niveau', 'valideur', 'publieur'])
            ->where('id_niveau', $donnees['id_niveau'])
            ->when($donnees['id_annee_academique'] ?? null, fn ($q, $annee) => $q->whereHas('inscription', fn ($i) => $i->where('id_annee_academique', $annee)))
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'bulletins' => $bulletins->map(fn (Bulletin $b) => $this->ligne($b))->values(),
        ]);
    }

    /** Le document du bulletin, sous la forme que lit l'étudiant. */
    public function show(int $bulletin): JsonResponse
    {
        $item = Bulletin::find($bulletin);
        if (! $item || ! $item->inscription) {
            return response()->json(['code' => 'BULLETIN_INTROUVABLE', 'message' => 'Bulletin introuvable.'], 404);
        }

        return response()->json($this->document->detail($item));
    }

    private function ligne(Bulletin $bulletin): array
    {
        $inscription = $bulletin->inscription;
        $etudiant = $inscription?->etudiant;
        $moyenne = $bulletin->moyenne !== null ? (float) $bulletin->moyenne : null;

        return [
            'id' => $bulletin->id,
            'periode' => $bulletin->periode,
            'fin_de_cycle' => $bulletin->estFinDeCycle(),
            'statut' => $bulletin->statut,
            'moyenne' => $moyenne,
            'mention' => MentionBulletinService::afficher($bulletin->mention, $moyenne),
            'date_publication' => $bulletin->date_publication?->toIso8601String(),
            'valide_par' => $this->personne($bulletin->valideur),
            'valide_le' => $bulletin->valide_le?->toIso8601String(),
            'publie_par' => $this->personne($bulletin->publieur),
            'etudiant' => $etudiant?->only(['id', 'matricule', 'nom', 'prenoms']),
            'promotion' => $inscription?->promotion?->only(['id', 'code', 'num_promotion']),
            'annee_academique' => $inscription?->anneeAcademique?->only(['id', 'libelle']),
            'niveau' => $bulletin->niveau?->only(['id', 'code', 'libelle', 'rang']),
        ];
    }

    private function personne(?User $utilisateur): ?array
    {
        return $utilisateur ? ['id' => $utilisateur->id, 'nom' => $utilisateur->nom, 'prenoms' => $utilisateur->prenoms] : null;
    }
}
