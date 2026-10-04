<?php

namespace App\Http\Controllers\Api\V1\Etudiant;

use App\Http\Controllers\Controller;
use App\Models\CoursAFaire;
use App\Models\Etudiant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MesCoursAFaireController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $filtres = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $etudiant = Etudiant::where('user_id', $request->user()->id)->firstOrFail();
        $items = CoursAFaire::query()
            ->where('id_etudiant', $etudiant->id)
            ->where('statut', 'a_faire')
            ->whereHas('seance')
            ->with(['matiere', 'cours.module', 'seance.enseignant'])
            ->orderByDesc('id')
            ->paginate($filtres['per_page'] ?? 15);

        return response()->json([
            'etudiant' => $etudiant->only(['id', 'matricule', 'nom', 'prenoms']),
            'cours_a_faire' => $items->getCollection()->map(fn (CoursAFaire $item) => [
                ...$item->only(['id', 'id_cours', 'id_matiere', 'id_seance', 'statut', 'motif']),
                'matiere' => $item->matiere?->only(['id', 'code', 'libelle']),
                'cours' => $item->cours?->only(['id', 'code', 'libelle', 'volume_horaire', 'coefficient']),
                'module' => $item->cours?->module?->only(['id', 'code', 'libelle']),
                'seance' => $item->seance->only(['id', 'date_prevue', 'heure_debut_prevue', 'heure_fin_prevue', 'date_effective', 'theme_traite', 'supports_pedagogiques']),
                'enseignant' => $item->seance->enseignant?->only(['id', 'nom', 'prenoms']),
            ]),
            'meta' => [
                'current_page' => $items->currentPage(), 'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(), 'total' => $items->total(),
                'from' => $items->firstItem(), 'to' => $items->lastItem(),
            ],
        ]);
    }
}
