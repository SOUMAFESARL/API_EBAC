<?php

namespace App\Http\Controllers\Api\V1\Stage;

use App\Http\Controllers\Controller;
use App\Models\RapportStage;
use App\Models\SemaineStage;
use App\Models\Stage;
use App\Support\Stage\DossiersStage as D;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Espace Étudiant : le journal de stage et les rapports du stagiaire
 * connecté — 09/11/2026. Aucun identifiant n'est reçu : le stage est celui
 * du porteur du jeton, jamais un numéro à deviner.
 *
 * Une semaine se consigne à partir du jour où elle commence ; un retard
 * est signalé à l'écran, jamais refusé. Une semaine validée ne se
 * réécrit plus ; l'évaluation remise, tout le dossier est clos.
 */
class StageEtudiantController extends Controller
{
    private const DISQUE = 'local';

    public function show(Request $request): JsonResponse
    {
        $stage = $this->monStage($request);
        if ($stage instanceof JsonResponse) {
            return $stage;
        }

        return response()->json(D::dossierStage($stage->id));
    }

    public function consigner(Request $request): JsonResponse
    {
        $stage = $this->monStage($request);
        if ($stage instanceof JsonResponse) {
            return $stage;
        }
        if ($stage->clos()) {
            return D::refus(409, 'STAGE_CLOS', 'L évaluation finale est remise : le journal est clos.');
        }

        $donnees = $request->validate([
            'numero' => ['required', 'integer'],
            'theme' => ['required', 'string', 'min:5', 'max:120'],
            'activites' => ['required', 'string', 'min:30', 'max:2000'],
            'heures' => ['required', 'integer', 'min:1', 'max:80'],
        ]);
        $numero = (int) $donnees['numero'];
        $campagne = $stage->campagne;

        if ($numero < 1 || $numero > $campagne->semainesPrevues()) {
            return D::refus(409, 'SEMAINE_HORS_PERIODE', 'Cette semaine ne fait pas partie du stage.');
        }
        if ($campagne->date_debut->copy()->addDays(($numero - 1) * 7)->toDateString() > now()->toDateString()) {
            return D::refus(409, 'SEMAINE_A_VENIR', 'Cette semaine n a pas commencé.');
        }

        $existante = SemaineStage::query()->where('id_stage', $stage->id)->where('numero', $numero)->first();
        if ($existante?->statut === 'validee') {
            return D::refus(409, 'SEMAINE_DEJA_VALIDEE', 'Cette semaine est validée : elle ne se reprend pas.');
        }

        // Une semaine complétée repart à la décision de l'église ;
        // l'observation reste, c'est la consigne à laquelle elle répond.
        SemaineStage::updateOrCreate(
            ['id_stage' => $stage->id, 'numero' => $numero],
            [
                'theme' => trim($donnees['theme']),
                'activites' => trim($donnees['activites']),
                'heures' => (int) $donnees['heures'],
                'statut' => 'a-valider',
                'observation' => $existante?->observation,
                'statuee_le' => null,
                'statuee_par' => null,
            ],
        );

        return response()->json(D::dossierStage($stage->id));
    }

    /** Dépose un rapport ; un redépôt remplace le fichier tant que le stage n'est pas clos. */
    public function deposer(Request $request): JsonResponse
    {
        $stage = $this->monStage($request);
        if ($stage instanceof JsonResponse) {
            return $stage;
        }
        if ($stage->clos()) {
            return D::refus(409, 'STAGE_CLOS', 'L évaluation finale est remise : le dossier est clos.');
        }

        $donnees = $request->validate([
            'type' => ['required', Rule::in(array_keys(RapportStage::TYPES))],
            'fichier' => ['required', 'file', 'extensions:'.implode(',', RapportStage::EXTENSIONS), 'max:'.RapportStage::TAILLE_MAX_KO],
        ]);

        $fichier = $request->file('fichier');
        $chemin = $fichier->store('stages/rapports', self::DISQUE);
        $ancien = RapportStage::query()->where('id_stage', $stage->id)->where('type', $donnees['type'])->first();

        try {
            DB::transaction(fn () => RapportStage::updateOrCreate(
                ['id_stage' => $stage->id, 'type' => $donnees['type']],
                [
                    'chemin' => $chemin,
                    'nom_fichier' => $fichier->getClientOriginalName(),
                    'taille_octets' => $fichier->getSize(),
                ],
            ));
        } catch (\Throwable $erreur) {
            Storage::disk(self::DISQUE)->delete($chemin);
            throw $erreur;
        }

        if ($ancien && $ancien->chemin !== $chemin) {
            Storage::disk(self::DISQUE)->delete($ancien->chemin);
        }

        return response()->json(D::dossierStage($stage->id));
    }

    public function rapport(Request $request, string $type): Response
    {
        $stage = $this->monStage($request);
        if ($stage instanceof JsonResponse) {
            return $stage;
        }
        $rapport = RapportStage::query()->where('id_stage', $stage->id)->where('type', $type)->first();
        if (! $rapport || ! Storage::disk(self::DISQUE)->exists($rapport->chemin)) {
            return D::refus(404, 'RAPPORT_INTROUVABLE', 'Ce rapport n est pas déposé.');
        }

        return Storage::disk(self::DISQUE)->download($rapport->chemin, $rapport->nom_fichier, ['Cache-Control' => 'no-store, private']);
    }

    private function monStage(Request $request): Stage|JsonResponse
    {
        $etudiant = D::etudiantDuCompte($request->user());
        $stage = $etudiant ? D::stageDe($etudiant) : null;

        return $stage
            ? $stage->load('campagne')
            : D::refus(404, 'STAGE_ABSENT', 'Aucun stage n est ouvert à votre nom.');
    }
}
