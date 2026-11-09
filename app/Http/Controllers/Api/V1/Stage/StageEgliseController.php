<?php

namespace App\Http\Controllers\Api\V1\Stage;

use App\Http\Controllers\Controller;
use App\Models\Eglise;
use App\Models\RapportStage;
use App\Models\SemaineStage;
use App\Models\Stage;
use App\Support\Stage\DossiersStage as D;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Portail Église : les stagiaires qu'elle accueille (CDC §7.2, étapes 5 à
 * 8) — 09/11/2026. Aucun identifiant d'église n'est reçu : elle se déduit
 * du compte (`DossiersStage::egliseDuCompte`). Un stage d'une autre église
 * répond comme un stage inexistant.
 */
class StageEgliseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $eglise = D::egliseDuCompte($request->user());
        if ($eglise instanceof JsonResponse) {
            return $eglise;
        }

        return response()->json([
            'eglise' => ['id' => $eglise->id, 'nom' => $eglise->nom],
            'stages' => D::requeteStages()->where('id_eglise', $eglise->id)->orderByDesc('id')->get()
                ->map(fn (Stage $s) => D::stage($s))->values(),
        ]);
    }

    public function show(Request $request, int $stage): JsonResponse
    {
        $item = $this->stageAccueilli($request, $stage);
        if ($item instanceof JsonResponse) {
            return $item;
        }

        return response()->json(D::dossierStage($item->id));
    }

    /** Valide une semaine, ou la renvoie commentée. Une semaine validée ne se reprend pas. */
    public function statuer(Request $request, int $semaine): JsonResponse
    {
        $item = SemaineStage::with('stage.campagne')->find($semaine);
        $eglise = D::egliseDuCompte($request->user());
        if ($eglise instanceof JsonResponse) {
            return $eglise;
        }
        if (! $item || $item->stage->id_eglise !== $eglise->id) {
            return D::refus(404, 'SEMAINE_INTROUVABLE', 'Semaine introuvable.');
        }
        if ($item->statut === 'validee') {
            return D::refus(409, 'SEMAINE_DEJA_VALIDEE', 'Cette semaine est validée : elle ne se reprend pas.');
        }

        $donnees = $request->validate([
            'decision' => ['required', Rule::in(['valider', 'commenter'])],
            'observation' => ['nullable', 'string', 'max:2000'],
        ]);
        $observation = trim((string) ($donnees['observation'] ?? ''));
        if ($donnees['decision'] === 'commenter' && $observation === '') {
            return D::refus(409, 'OBSERVATION_REQUISE', 'Dites ce qui doit être complété.');
        }

        $item->update([
            'statut' => $donnees['decision'] === 'valider' ? 'validee' : 'commentee',
            'observation' => $observation,
            'statuee_le' => now(),
            'statuee_par' => $request->user()->id,
        ]);
        $item->load('decideur');

        return response()->json(['semaine' => D::semaine($item, $item->stage)]);
    }

    /**
     * Remet l'évaluation finale (part Église, 85 %). Pas d'évaluation sans
     * journal : toutes les semaines prévues consignées et validées. La note
     * est recalculée ici, jamais reçue.
     */
    public function evaluer(Request $request, int $stage): JsonResponse
    {
        $item = $this->stageAccueilli($request, $stage);
        if ($item instanceof JsonResponse) {
            return $item;
        }
        if ($item->clos()) {
            return D::refus(409, 'EVALUATION_DEJA_REMISE', 'L évaluation finale est déjà remise.');
        }
        if ($item->semaines()->where('statut', '!=', 'validee')->exists()) {
            return D::refus(409, 'JOURNAL_INCOMPLET', 'Des semaines du journal restent à statuer.');
        }
        if ($item->semaines()->count() < $item->campagne->semainesPrevues()) {
            return D::refus(409, 'STAGE_EN_COURS', 'Le stage n a pas atteint son terme.');
        }

        $donnees = $request->validate([
            ...D::reglesGrille(),
            'appreciationGenerale' => ['nullable', 'string', 'max:2000'],
        ]);
        if (! D::grilleConforme($donnees['criteres'], 'eglise')) {
            return D::refus(409, 'CRITERE_INCONNU', 'Les critères envoyés ne sont pas ceux de la part de l église.');
        }

        $lignes = D::lignes($donnees['criteres']);
        $item->update([
            'evaluation_criteres' => $lignes,
            'evaluation_appreciation' => trim((string) ($donnees['appreciationGenerale'] ?? '')),
            'evaluation_note' => D::note($lignes),
            'evaluation_remise_le' => now(),
            'evaluation_remise_par' => $request->user()->id,
        ]);

        return response()->json(['stage' => D::stage(D::requeteStages()->findOrFail($item->id))]);
    }

    /** Télécharge un rapport déposé par le stagiaire. */
    public function rapport(Request $request, int $stage, string $type): Response
    {
        $item = $this->stageAccueilli($request, $stage);
        if ($item instanceof JsonResponse) {
            return $item;
        }
        $rapport = RapportStage::query()->where('id_stage', $item->id)->where('type', $type)->first();
        if (! $rapport || ! Storage::disk('local')->exists($rapport->chemin)) {
            return D::refus(404, 'RAPPORT_INTROUVABLE', 'Ce rapport n est pas déposé.');
        }

        return Storage::disk('local')->download($rapport->chemin, $rapport->nom_fichier, ['Cache-Control' => 'no-store, private']);
    }

    private function stageAccueilli(Request $request, int $stage): Stage|JsonResponse
    {
        $eglise = D::egliseDuCompte($request->user());
        if ($eglise instanceof JsonResponse) {
            return $eglise;
        }
        /** @var Eglise $eglise */
        $item = Stage::with('campagne')->find($stage);

        return $item && $item->id_eglise === $eglise->id
            ? $item
            : D::refus(404, 'STAGE_INTROUVABLE', 'Stage introuvable.');
    }
}
