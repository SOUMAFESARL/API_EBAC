<?php

namespace App\Support\Stage;

use App\Models\AffectationStage;
use App\Models\CampagneStage;
use App\Models\Eglise;
use App\Models\Etudiant;
use App\Models\Promotion;
use App\Models\RapportStage;
use App\Models\SemaineStage;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Ce que les quatre contrôleurs du stage partagent : la grille du CDC
 * §7.4, la forme des réponses, et le rattachement d'un compte Église à
 * son église.
 *
 * La grille vit ici côté service, comme `CRITERES_EVALUATION` côté front :
 * les deux doivent porter les mêmes codes et les mêmes pondérations.
 */
final class DossiersStage
{
    /** Code → [évaluateur, pondération en %]. Totalise 100. */
    public const GRILLE = [
        'discipline' => ['eglise', 15],
        'engagement' => ['eglise', 20],
        'predication' => ['eglise', 20],
        'maturite' => ['eglise', 15],
        'leadership' => ['eglise', 15],
        'journal' => ['administration', 15],
    ];

    /** @return list<string> */
    public static function criteresDe(string $evaluateur): array
    {
        return array_keys(array_filter(self::GRILLE, fn (array $c) => $c[0] === $evaluateur));
    }

    /**
     * Les lignes envoyées sont-elles exactement les critères de cet
     * évaluateur, chacun une fois ?
     *
     * @param  list<array{code: string}>  $lignes
     */
    public static function grilleConforme(array $lignes, string $evaluateur): bool
    {
        $codes = array_map(fn (array $l) => $l['code'], $lignes);
        $attendus = self::criteresDe($evaluateur);
        sort($codes);
        sort($attendus);

        return $codes === $attendus;
    }

    /**
     * Moyenne pondérée sur 20, arrondie au dixième — même calcul que
     * `moyenneCriteres` du front.
     *
     * @param  list<array{code: string, note: float|int}>  $lignes
     */
    public static function note(array $lignes): ?float
    {
        $somme = 0.0;
        $poids = 0;
        foreach ($lignes as $ligne) {
            $p = self::GRILLE[$ligne['code']][1] ?? 0;
            $somme += (float) $ligne['note'] * $p;
            $poids += $p;
        }

        return $poids === 0 ? null : round($somme / $poids, 1);
    }

    /** @return list<array{code: string, note: float, appreciation: string}> */
    public static function lignes(array $criteres): array
    {
        return array_values(array_map(fn (array $l) => [
            'code' => (string) $l['code'],
            'note' => round((float) $l['note'], 2),
            'appreciation' => trim((string) ($l['appreciation'] ?? '')),
        ], $criteres));
    }

    /** Règles de validation d'une grille envoyée. */
    public static function reglesGrille(): array
    {
        return [
            'criteres' => ['required', 'array', 'min:1'],
            'criteres.*.code' => ['required', 'string', 'distinct'],
            'criteres.*.note' => ['required', 'numeric', 'min:0', 'max:20'],
            'criteres.*.appreciation' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /* --- Rattachement du compte Église ------------------------------------ */

    /**
     * L'église d'un compte Église : celle dont un représentant porte
     * l'adresse du compte, à défaut celle dont c'est l'adresse propre.
     * Une adresse déclarée dans deux dossiers ne rattache à aucun — ouvrir
     * l'un montrerait les stagiaires d'une paroisse à une autre.
     */
    public static function egliseDuCompte(User $compte): Eglise|JsonResponse
    {
        $email = mb_strtolower(trim((string) $compte->email));
        if ($email === '') {
            return self::refus(404, 'EGLISE_NON_RATTACHEE', 'Aucune église ne déclare l adresse de ce compte.');
        }

        $eglises = Eglise::query()->get(['id', 'nom', 'email', 'representants', 'statut', 'ville_commune']);
        $parRepresentant = $eglises->filter(fn (Eglise $e) => collect($e->representants ?? [])
            ->contains(fn ($r) => mb_strtolower(trim((string) ($r['email'] ?? ''))) === $email));
        $candidates = $parRepresentant->isNotEmpty()
            ? $parRepresentant
            : $eglises->filter(fn (Eglise $e) => mb_strtolower(trim((string) $e->email)) === $email);

        if ($candidates->count() > 1) {
            return self::refus(409, 'EGLISE_AMBIGUE', 'Cette adresse est déclarée dans plusieurs dossiers d église.');
        }

        return $candidates->first()
            ?? self::refus(404, 'EGLISE_NON_RATTACHEE', 'Aucune église ne déclare l adresse de ce compte.');
    }

    public static function etudiantDuCompte(User $compte): ?Etudiant
    {
        return Etudiant::query()->where('user_id', $compte->id)->first();
    }

    /** Le stage le plus récent de l'étudiant, s'il en a un. */
    public static function stageDe(Etudiant $etudiant): ?Stage
    {
        return Stage::query()->where('id_etudiant', $etudiant->id)->latest('id')->first();
    }

    /* --- Présentation ------------------------------------------------------ */

    public static function personne(?User $utilisateur): ?array
    {
        return $utilisateur
            ? ['id' => $utilisateur->id, 'nom' => $utilisateur->nom, 'prenoms' => $utilisateur->prenoms]
            : null;
    }

    public static function promotion(?Promotion $promotion): ?array
    {
        return $promotion
            ? ['id' => $promotion->id, 'code' => $promotion->code, 'num_promotion' => $promotion->num_promotion]
            : null;
    }

    public static function niveau(?Promotion $promotion): ?array
    {
        $niveau = $promotion?->niveau;

        return $niveau ? ['id' => $niveau->id, 'libelle' => $niveau->libelle] : null;
    }

    public static function etudiant(?Etudiant $etudiant): ?array
    {
        return $etudiant
            ? ['id' => $etudiant->id, 'matricule' => $etudiant->matricule, 'nom' => $etudiant->nom, 'prenoms' => $etudiant->prenoms]
            : null;
    }

    /** Requête d'une campagne avec ses compteurs, tenus par le service. */
    public static function requeteCampagnes()
    {
        return CampagneStage::query()
            ->with(['promotion.niveau', 'auteur'])
            ->withCount([
                'affectations as effectif',
                'affectations as affectes' => fn ($q) => $q->whereNotNull('id_eglise')
                    ->whereNotNull('tuteur_nom')->where('tuteur_nom', '!=', ''),
                'affectations as valides' => fn ($q) => $q->where('statut', 'validee'),
            ]);
    }

    public static function campagne(CampagneStage $campagne): array
    {
        return [
            'id' => $campagne->id,
            'promotion' => self::promotion($campagne->promotion),
            'niveau' => self::niveau($campagne->promotion),
            'debut' => $campagne->date_debut->toDateString(),
            'fin' => $campagne->date_fin->toDateString(),
            'semaines_prevues' => $campagne->semainesPrevues(),
            'statut' => $campagne->statut(),
            'effectif' => (int) ($campagne->effectif ?? 0),
            'affectes' => (int) ($campagne->affectes ?? 0),
            'valides' => (int) ($campagne->valides ?? 0),
            'ouverte_le' => $campagne->created_at?->toIso8601String(),
            'ouverte_par' => self::personne($campagne->auteur),
        ];
    }

    public static function affectation(AffectationStage $affectation): array
    {
        $eglise = $affectation->eglise;

        return [
            'id' => $affectation->id,
            'id_campagne' => $affectation->id_campagne,
            'etudiant' => self::etudiant($affectation->etudiant),
            'eglise' => $eglise ? ['id' => $eglise->id, 'nom' => $eglise->nom, 'ville' => $eglise->ville_commune] : null,
            'tuteur_nom' => $affectation->tuteur_nom,
            'tuteur_telephone' => $affectation->tuteur_telephone,
            'tuteur_email' => $affectation->tuteur_email,
            'statut' => $affectation->statut,
            'validee_le' => $affectation->validee_le?->toIso8601String(),
            'id_stage' => $affectation->stage?->id,
        ];
    }

    /** Campagne et affectations en une réponse : la contrainte 3G proscrit la cascade. */
    public static function dossierCampagne(int $campagneId): array
    {
        $campagne = self::requeteCampagnes()->findOrFail($campagneId);
        $affectations = AffectationStage::query()
            ->with(['etudiant', 'eglise', 'stage'])
            ->where('id_campagne', $campagneId)
            ->get()
            ->sortBy(fn (AffectationStage $a) => mb_strtolower(($a->etudiant?->nom ?? '').' '.($a->etudiant?->prenoms ?? '')))
            ->values();

        return [
            'campagne' => self::campagne($campagne),
            'affectations' => $affectations->map(fn (AffectationStage $a) => self::affectation($a))->values(),
        ];
    }

    public static function requeteStages()
    {
        return Stage::query()
            ->with(['campagne.promotion.niveau', 'etudiant', 'eglise', 'evaluateur', 'agentPartAdministration', 'valideur'])
            ->withCount([
                'semaines as semaines_consignees',
                'semaines as semaines_validees' => fn ($q) => $q->where('statut', 'validee'),
            ]);
    }

    public static function stage(Stage $stage): array
    {
        $campagne = $stage->campagne;

        return [
            'id' => $stage->id,
            'id_campagne' => $stage->id_campagne,
            'etudiant' => self::etudiant($stage->etudiant),
            'promotion' => self::promotion($campagne->promotion),
            'niveau' => self::niveau($campagne->promotion),
            'eglise' => $stage->eglise ? ['id' => $stage->eglise->id, 'nom' => $stage->eglise->nom] : null,
            'encadreur' => $stage->encadreur,
            'debut' => $campagne->date_debut->toDateString(),
            'fin' => $campagne->date_fin->toDateString(),
            'semaines_prevues' => $campagne->semainesPrevues(),
            'statut' => $stage->statut(),
            'semaines_consignees' => (int) ($stage->semaines_consignees ?? 0),
            'semaines_validees' => (int) ($stage->semaines_validees ?? 0),
            'evaluation' => $stage->evaluation_remise_le ? [
                'criteres' => $stage->evaluation_criteres ?? [],
                'appreciation_generale' => (string) $stage->evaluation_appreciation,
                'note' => round((float) $stage->evaluation_note, 1),
                'remise_le' => $stage->evaluation_remise_le->toIso8601String(),
                'remise_par' => self::personne($stage->evaluateur),
            ] : null,
            'part_administration' => $stage->part_admin_portee_le ? [
                'criteres' => $stage->part_admin_criteres ?? [],
                'appreciation' => (string) $stage->part_admin_appreciation,
                'portee_le' => $stage->part_admin_portee_le->toIso8601String(),
                'portee_par' => self::personne($stage->agentPartAdministration),
            ] : null,
            'validation' => $stage->validation_prononcee_le ? [
                'decision' => $stage->validation_decision,
                'motif' => (string) $stage->validation_motif,
                'prononcee_le' => $stage->validation_prononcee_le->toIso8601String(),
                'prononcee_par' => self::personne($stage->valideur),
            ] : null,
        ];
    }

    public static function semaine(SemaineStage $semaine, Stage $stage): array
    {
        $debut = $stage->campagne->date_debut->copy()->addDays(($semaine->numero - 1) * 7);

        return [
            'id' => $semaine->id,
            'numero' => $semaine->numero,
            'debut' => $debut->toDateString(),
            'fin' => $debut->copy()->addDays(6)->toDateString(),
            'theme' => $semaine->theme,
            'activites' => $semaine->activites,
            'heures' => $semaine->heures,
            'statut' => $semaine->statut,
            'observation' => (string) $semaine->observation,
            'statuee_le' => $semaine->statuee_le?->toIso8601String(),
            'statuee_par' => self::personne($semaine->decideur),
        ];
    }

    /** Stage, journal (le plus récent d'abord) et documents attendus. */
    public static function dossierStage(int $stageId): array
    {
        $stage = self::requeteStages()->findOrFail($stageId);
        $semaines = SemaineStage::query()->with('decideur')->where('id_stage', $stageId)->orderByDesc('numero')->get();
        $rapports = RapportStage::query()->where('id_stage', $stageId)->get()->keyBy('type');

        return [
            'stage' => self::stage($stage),
            'semaines' => $semaines->map(fn (SemaineStage $s) => self::semaine($s, $stage))->values(),
            'rapports' => collect(RapportStage::TYPES)->map(fn (string $titre, string $type) => [
                'type' => $type,
                'titre' => $titre,
                'nom_fichier' => $rapports[$type]->nom_fichier ?? null,
                'taille_octets' => (int) ($rapports[$type]->taille_octets ?? 0),
                'depose_le' => isset($rapports[$type]) ? $rapports[$type]->updated_at?->toIso8601String() : null,
            ])->values(),
        ];
    }

    public static function refus(int $statut, string $code, string $message): JsonResponse
    {
        return response()->json(['code' => $code, 'message' => $message], $statut);
    }
}
