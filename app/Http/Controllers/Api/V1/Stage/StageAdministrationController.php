<?php

namespace App\Http\Controllers\Api\V1\Stage;

use App\Http\Controllers\Controller;
use App\Models\Stage;
use App\Support\Stage\DossiersStage as D;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Stages de l'école, côté administration (CDC §7.2, étapes 8 et 10) —
 * 09/11/2026 : la part de l'Administration EBAC dans la grille (15 %),
 * puis la décision de la Direction sur la matière Stage. Ni l'une ni
 * l'autre ne se reprend.
 */
class StageAdministrationController extends Controller
{
    /** Tous les stages, toutes églises confondues, les plus récents d'abord. */
    public function index(): JsonResponse
    {
        return response()->json([
            'stages' => D::requeteStages()->orderByDesc('id')->get()
                ->map(fn (Stage $s) => D::stage($s))->values(),
        ]);
    }

    /** La part EBAC vient après l'évaluation de l'église. La note n'est pas envoyée : elle se recalcule. */
    public function partAdministration(Request $request, int $stage): JsonResponse
    {
        $item = Stage::find($stage);
        if (! $item) {
            return D::refus(404, 'STAGE_INTROUVABLE', 'Stage introuvable.');
        }
        if (! $item->evaluation_remise_le) {
            return D::refus(409, 'EVALUATION_ATTENDUE', 'L église d accueil n a pas encore remis son évaluation.');
        }
        if ($item->part_admin_portee_le) {
            return D::refus(409, 'PART_ADMINISTRATION_PORTEE', 'La part de l administration est déjà portée.');
        }

        $donnees = $request->validate([
            ...D::reglesGrille(),
            'appreciation' => ['nullable', 'string', 'max:2000'],
        ]);
        if (! D::grilleConforme($donnees['criteres'], 'administration')) {
            return D::refus(409, 'CRITERE_INCONNU', 'Les critères envoyés ne sont pas ceux de la part de l administration.');
        }

        $item->update([
            'part_admin_criteres' => D::lignes($donnees['criteres']),
            'part_admin_appreciation' => trim((string) ($donnees['appreciation'] ?? '')),
            'part_admin_portee_le' => now(),
            'part_admin_portee_par' => $request->user()->id,
        ]);

        return response()->json(['stage' => D::stage(D::requeteStages()->findOrFail($item->id))]);
    }

    /** Valide ou refuse la matière Stage. Acte de la Direction, sans retour. */
    public function valider(Request $request, int $stage): JsonResponse
    {
        $item = Stage::find($stage);
        if (! $item) {
            return D::refus(404, 'STAGE_INTROUVABLE', 'Stage introuvable.');
        }
        if ($item->validation_prononcee_le) {
            return D::refus(409, 'STAGE_DEJA_VALIDE', 'La matière Stage est déjà tranchée.');
        }
        if (! $item->evaluation_remise_le || ! $item->part_admin_portee_le) {
            return D::refus(409, 'NOTE_INCOMPLETE', 'La note finale n est pas complète.');
        }

        $donnees = $request->validate([
            'decision' => ['required', Rule::in(['validee', 'non-validee'])],
            'motif' => ['nullable', 'string', 'max:2000'],
        ]);
        $motif = trim((string) ($donnees['motif'] ?? ''));
        if ($donnees['decision'] === 'non-validee' && $motif === '') {
            return D::refus(409, 'MOTIF_REQUIS', 'Une non-validation exige un motif.');
        }

        $item->update([
            'validation_decision' => $donnees['decision'],
            'validation_motif' => $motif,
            'validation_prononcee_le' => now(),
            'validation_prononcee_par' => $request->user()->id,
        ]);

        return response()->json(['stage' => D::stage(D::requeteStages()->findOrFail($item->id))]);
    }
}
