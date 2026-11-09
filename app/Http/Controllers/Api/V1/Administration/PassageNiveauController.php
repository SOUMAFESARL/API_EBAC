<?php

namespace App\Http\Controllers\Api\V1\Administration;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Services\PassageNiveauService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PassageNiveauController extends Controller
{
    private const MESSAGES = [
        'PROMOTION_INTROUVABLE' => 'Promotion introuvable ou sans étudiant actif.',
        'PROMOTION_CLOSE' => 'La promotion ne parcourt plus le cursus.',
        'DERNIER_NIVEAU' => 'La promotion occupe le dernier niveau du cursus : aucun passage.',
        'ANNEE_SUIVANTE_ABSENTE' => 'Créez l’année académique suivante avant le passage.',
        'RAPPORT_PERIME' => 'La situation a changé depuis le rapport relu.',
        'NOTES_EN_ATTENTE' => 'Des notes de l’année ne sont pas validées : confirmation requise.',
        'PASSAGE_DEJA_EFFECTUE' => 'Le passage de cette promotion a déjà été effectué.',
    ];

    public function __construct(private readonly PassageNiveauService $passages) {}

    public function index(): JsonResponse
    {
        return response()->json(['promotions' => $this->passages->promotionsEligibles()]);
    }

    public function show(int $promotion): JsonResponse
    {
        return response()->json($this->passages->rapport(Promotion::findOrFail($promotion)));
    }

    public function executer(Request $request, int $promotion): JsonResponse
    {
        $donnees = $request->validate([
            'rapport_vu_le' => ['required', 'integer', 'min:1'],
            'malgre_notes_en_attente' => ['sometimes', 'boolean'],
        ]);

        $resultat = $this->passages->executer($promotion, (int) $donnees['rapport_vu_le'],
            (bool) ($donnees['malgre_notes_en_attente'] ?? false), $request->user()->id);

        if (isset($resultat['code'])) {
            return response()->json(['message' => self::MESSAGES[$resultat['code']], 'code' => $resultat['code']], $resultat['statut']);
        }

        return response()->json(['message' => 'Passage de niveau effectué.', 'bilan' => $resultat['bilan']]);
    }
}
