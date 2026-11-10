<?php

namespace App\Http\Controllers\Api\V1\Administration;

use App\Http\Controllers\Controller;
use App\Models\Matiere;
use App\Models\MatiereTerminee;
use App\Models\Promotion;
use App\Services\MatiereTermineeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MatiereTermineeController extends Controller
{
    public const MESSAGES = [
        'PROMOTION_CLOSE' => 'La promotion ne parcourt plus le cursus.',
        'NIVEAU_DIFFERENT' => 'Cette matière n’est pas au niveau actuel de la promotion.',
        'PASSAGE_EFFECTUE' => 'Le passage de niveau est fait : la matière ne se rouvre plus.',
    ];

    public function __construct(private readonly MatiereTermineeService $service) {}

    public function index(): JsonResponse
    {
        return response()->json(['promotions' => $this->service->promotions()]);
    }

    public function show(int $promotion): JsonResponse
    {
        return response()->json($this->service->etat(Promotion::findOrFail($promotion)));
    }

    public function store(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'id_matiere' => ['required', 'integer', Rule::exists('matieres', 'id')->whereNull('deleted_at')],
            'id_promotion' => ['required', 'integer', Rule::exists('promotions', 'id')->whereNull('deleted_at')],
        ]);

        return self::repondre($this->service->terminer(Matiere::findOrFail($donnees['id_matiere']),
            Promotion::findOrFail($donnees['id_promotion']), $request->user()->id), $this->service);
    }

    public function destroy(int $id): JsonResponse
    {
        $refus = $this->service->rouvrir(MatiereTerminee::findOrFail($id));
        if ($refus) {
            return response()->json(['message' => self::MESSAGES[$refus['code']], 'code' => $refus['code']], $refus['statut']);
        }

        return response()->json(['message' => 'Matière rouverte.']);
    }

    public static function repondre(array $resultat, MatiereTermineeService $service): JsonResponse
    {
        if (isset($resultat['code'])) {
            return response()->json(['message' => self::MESSAGES[$resultat['code']], 'code' => $resultat['code']], $resultat['statut']);
        }

        return response()->json(['message' => 'Matière marquée terminée.', 'terminee' => $service->presenter($resultat['terminee'])],
            $resultat['creee'] ? 201 : 200);
    }
}
