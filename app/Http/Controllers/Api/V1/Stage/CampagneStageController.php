<?php

namespace App\Http\Controllers\Api\V1\Stage;

use App\Http\Controllers\Controller;
use App\Models\AffectationStage;
use App\Models\CampagneStage;
use App\Models\Eglise;
use App\Models\Etudiant;
use App\Models\Promotion;
use App\Models\SemaineStage;
use App\Models\Stage;
use App\Support\Stage\DossiersStage as D;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Campagnes de stage et affectations (CDC §7.2, étapes 1 à 4) — 09/11/2026.
 *
 * Une campagne par promotion ; à l'ouverture, chaque étudiant inscrit dans
 * la promotion reçoit une affectation vierge. Les dates se reprennent tant
 * que la campagne n'est pas clôturée ; elle se supprime tant qu'aucune
 * affectation n'est validée. Une affectation validée ouvre le stage et ne
 * se reprend pas.
 */
class CampagneStageController extends Controller
{
    /** Campagnes, la plus récente d'abord. */
    public function index(): JsonResponse
    {
        return response()->json([
            'campagnes' => D::requeteCampagnes()->orderByDesc('date_debut')->orderByDesc('id')->get()
                ->map(fn (CampagneStage $c) => D::campagne($c))->values(),
        ]);
    }

    /**
     * Promotions sur lesquelles une campagne peut s'ouvrir, du niveau le
     * plus avancé au premier. Une promotion déjà servie reste listée avec
     * sa campagne : l'écran dit où elle est plutôt que de la faire
     * disparaître.
     */
    public function promotionsEligibles(): JsonResponse
    {
        $campagnes = CampagneStage::query()->pluck('id', 'id_promotion');
        $promotions = Promotion::query()->with('niveau')->get()
            ->sortBy([
                fn ($a, $b) => ($b->niveau?->rang ?? 0) <=> ($a->niveau?->rang ?? 0),
                fn ($a, $b) => ($b->num_promotion ?? 0) <=> ($a->num_promotion ?? 0),
            ]);

        return response()->json([
            'promotions' => $promotions->map(fn (Promotion $p) => [
                'promotion' => D::promotion($p),
                'niveau' => D::niveau($p),
                'effectif' => $this->etudiantsDe($p->id)->count(),
                'id_campagne' => $campagnes[$p->id] ?? null,
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'promotion_id' => ['required', 'integer'],
            'debut' => ['required', 'date_format:Y-m-d'],
            'fin' => ['required', 'date_format:Y-m-d'],
        ]);

        $promotion = Promotion::find($donnees['promotion_id']);
        if (! $promotion) {
            return D::refus(404, 'PROMOTION_INTROUVABLE', 'Promotion introuvable.');
        }
        if (CampagneStage::query()->where('id_promotion', $promotion->id)->exists()) {
            return D::refus(409, 'CAMPAGNE_EXISTE', 'Une campagne de stage est déjà ouverte pour cette promotion.');
        }
        if ($refus = $this->periodeRefusee($donnees['debut'], $donnees['fin'])) {
            return $refus;
        }

        $auteur = $request->user()->id;
        $campagne = DB::transaction(function () use ($promotion, $donnees, $auteur) {
            $campagne = CampagneStage::create([
                'id_promotion' => $promotion->id,
                'date_debut' => $donnees['debut'],
                'date_fin' => $donnees['fin'],
                'ouverte_par' => $auteur,
                'updated_by' => $auteur,
            ]);

            // Le rattachement est fait ici : tous les étudiants inscrits dans
            // la promotion, sans liste envoyée par l'écran.
            foreach ($this->etudiantsDe($promotion->id) as $etudiantId) {
                AffectationStage::create([
                    'id_campagne' => $campagne->id,
                    'id_etudiant' => $etudiantId,
                    'statut' => 'a-affecter',
                    'updated_by' => $auteur,
                ]);
            }

            return $campagne;
        });

        return response()->json(['campagne' => D::campagne(D::requeteCampagnes()->findOrFail($campagne->id))], 201);
    }

    public function show(int $campagne): JsonResponse
    {
        if (! CampagneStage::query()->whereKey($campagne)->exists()) {
            return D::refus(404, 'CAMPAGNE_INTROUVABLE', 'Campagne de stage introuvable.');
        }

        return response()->json(D::dossierCampagne($campagne));
    }

    /**
     * Reprend les dates d'une campagne non clôturée. Une fois un journal
     * entamé, le début ne bouge plus et la fin ne recule pas sous la
     * dernière semaine consignée : les semaines se datent depuis le début
     * de la campagne, et les déplacer réécrirait ce que l'église a statué.
     */
    public function update(Request $request, int $campagne): JsonResponse
    {
        $item = CampagneStage::find($campagne);
        if (! $item) {
            return D::refus(404, 'CAMPAGNE_INTROUVABLE', 'Campagne de stage introuvable.');
        }
        if ($item->statut() === 'cloturee') {
            return D::refus(409, 'CAMPAGNE_CLOTUREE', 'Cette campagne est clôturée.');
        }

        $donnees = $request->validate([
            'debut' => ['required', 'date_format:Y-m-d'],
            'fin' => ['required', 'date_format:Y-m-d'],
        ]);
        if ($refus = $this->periodeRefusee($donnees['debut'], $donnees['fin'])) {
            return $refus;
        }

        $derniere = (int) SemaineStage::query()
            ->whereIn('id_stage', Stage::query()->where('id_campagne', $item->id)->select('id'))
            ->max('numero');
        if ($derniere > 0 && (
            $donnees['debut'] !== $item->date_debut->toDateString()
            || CampagneStage::semainesEntre($donnees['debut'], $donnees['fin']) < $derniere
        )) {
            return D::refus(409, 'JOURNAL_ENTAME', 'Des semaines du journal sont déjà consignées : le début ne bouge plus et la fin ne recule pas sous la dernière semaine consignée.');
        }

        $item->update([
            'date_debut' => $donnees['debut'],
            'date_fin' => $donnees['fin'],
            'updated_by' => $request->user()->id,
        ]);

        return response()->json(['campagne' => D::campagne(D::requeteCampagnes()->findOrFail($item->id))]);
    }

    /**
     * Supprime une campagne tant que rien n'est engagé : ni clôturée, ni
     * une seule affectation validée — une validation a notifié l'église et
     * ouvert le journal du stagiaire. Ses affectations partent avec elle.
     */
    public function destroy(int $campagne): JsonResponse
    {
        $item = CampagneStage::find($campagne);
        if (! $item) {
            return D::refus(404, 'CAMPAGNE_INTROUVABLE', 'Campagne de stage introuvable.');
        }
        if ($item->statut() === 'cloturee') {
            return D::refus(409, 'CAMPAGNE_CLOTUREE', 'Cette campagne est clôturée.');
        }
        if ($item->affectations()->where('statut', 'validee')->exists() || $item->stages()->exists()) {
            return D::refus(409, 'CAMPAGNE_ENGAGEE', 'Une affectation de cette campagne est validée : la campagne ne se supprime plus.');
        }

        DB::transaction(function () use ($item) {
            $item->affectations()->delete();
            $item->delete();
        });

        return response()->json(['id' => $campagne]);
    }

    /* --- Affectations ------------------------------------------------------ */

    /** Affecte un étudiant à une église et à un tuteur. Reprenable jusqu'à la validation. */
    public function affecter(Request $request, int $affectation): JsonResponse
    {
        $item = AffectationStage::with('campagne')->find($affectation);
        if (! $item) {
            return D::refus(404, 'AFFECTATION_INTROUVABLE', 'Affectation introuvable.');
        }
        if ($refus = $this->affectationFermee($item)) {
            return $refus;
        }

        $donnees = $request->validate([
            'eglise_id' => ['required', 'integer'],
            'tuteur_nom' => ['nullable', 'string', 'max:160'],
            'tuteur_telephone' => ['nullable', 'string', 'max:40'],
            'tuteur_email' => ['nullable', 'email', 'max:180'],
        ]);
        if (trim((string) ($donnees['tuteur_nom'] ?? '')) === '') {
            return D::refus(409, 'AFFECTATION_INCOMPLETE', 'Le tuteur référent est obligatoire.');
        }

        $eglise = Eglise::find($donnees['eglise_id']);
        if (! $eglise) {
            return D::refus(404, 'EGLISE_INTROUVABLE', 'Église introuvable.');
        }
        if ($eglise->statut !== 'Active') {
            return D::refus(409, 'EGLISE_INDISPONIBLE', 'Cette église est suspendue ou archivée.');
        }
        $occupees = AffectationStage::query()
            ->where('id_campagne', $item->id_campagne)
            ->where('id_eglise', $eglise->id)
            ->whereKeyNot($item->id)
            ->count();
        if ($occupees >= (int) $eglise->capacite_max_stagiaires) {
            return D::refus(409, 'EGLISE_COMPLETE', 'Cette église a atteint sa capacité d accueil pour la campagne.');
        }

        $item->update([
            'id_eglise' => $eglise->id,
            'tuteur_nom' => trim($donnees['tuteur_nom']),
            'tuteur_telephone' => $donnees['tuteur_telephone'] ?? null,
            'tuteur_email' => $donnees['tuteur_email'] ?? null,
            'statut' => 'affectee',
            'updated_by' => $request->user()->id,
        ]);

        return response()->json(D::dossierCampagne($item->id_campagne));
    }

    /** Valide une affectation : le stage s'ouvre, l'église le voit à son portail. Sans retour. */
    public function valider(Request $request, int $affectation): JsonResponse
    {
        $item = AffectationStage::with('campagne')->find($affectation);
        if (! $item) {
            return D::refus(404, 'AFFECTATION_INTROUVABLE', 'Affectation introuvable.');
        }
        if ($refus = $this->affectationFermee($item)) {
            return $refus;
        }
        if (! $item->complete()) {
            return D::refus(409, 'AFFECTATION_INCOMPLETE', 'L église d accueil et le tuteur référent sont obligatoires.');
        }

        DB::transaction(function () use ($item, $request) {
            $item->update([
                'statut' => 'validee',
                'validee_le' => now(),
                'validee_par' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
            Stage::create([
                'id_affectation' => $item->id,
                'id_campagne' => $item->id_campagne,
                'id_etudiant' => $item->id_etudiant,
                'id_eglise' => $item->id_eglise,
                'encadreur' => $item->tuteur_nom,
            ]);
        });

        return response()->json(D::dossierCampagne($item->id_campagne));
    }

    /* --- Outils ------------------------------------------------------------ */

    private function affectationFermee(AffectationStage $affectation): ?JsonResponse
    {
        if ($affectation->campagne->statut() === 'cloturee') {
            return D::refus(409, 'CAMPAGNE_CLOTUREE', 'Cette campagne est clôturée.');
        }
        if ($affectation->statut === 'validee') {
            return D::refus(409, 'AFFECTATION_DEJA_VALIDEE', 'Cette affectation est validée : elle ne se reprend pas.');
        }

        return null;
    }

    private function periodeRefusee(string $debut, string $fin): ?JsonResponse
    {
        return CampagneStage::semainesEntre($debut, $fin) < CampagneStage::SEMAINES_MIN
            ? D::refus(409, 'PERIODE_INVALIDE', 'La fin doit suivre le début d au moins '.CampagneStage::SEMAINES_MIN.' semaines.')
            : null;
    }

    /** Étudiants inscrits dans la promotion, une fois chacun. */
    private function etudiantsDe(int $promotionId)
    {
        return Etudiant::query()
            ->whereHas('inscriptions', fn ($q) => $q->where('id_promotion', $promotionId))
            ->orderBy('nom')->orderBy('prenoms')
            ->pluck('id');
    }
}
