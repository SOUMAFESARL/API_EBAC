<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Bulletin;
use App\Models\EpreuveFinDeCycle;
use App\Models\Etudiant;
use App\Models\HistoriqueBulletinFinDeCycle;
use App\Models\Inscription;
use App\Models\NoteFinDeCycle;
use App\Models\Promotion;
use App\Models\User;
use App\Services\MentionBulletinService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as Liste;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fin de cycle (bulletin de fin de cycle de 3e Année) — 09/11/2026.
 *
 * Le référentiel des épreuves (rapport de stage, prédication,
 * dissertation, connaissance biblique générale, moyenne générale
 * annuelle…) et la saisie de leurs notes, épreuve par épreuve, pour une
 * promotion. Chaque note garde qui l'a saisie.
 *
 * Une épreuve ne se supprime pas : elle se retire, et la dernière active
 * ne se retire pas. La moyenne de fin de cycle n'est pas stockée : elle
 * se calcule sur les épreuves actives.
 *
 * Circuit du bulletin (09/11/2026) : les notes complètes d'un étudiant
 * sont **validées** — figées sur un bulletin (période « Fin de cycle »,
 * statut « Validé ») que l'étudiant ne voit pas encore, et qui ne se
 * ressaisissent plus ; une validation se **renvoie en saisie** tant que le
 * bulletin n'est pas publié ; la **publication** le rend visible dans le
 * portail étudiant et le fige pour de bon. Chaque geste porte sur une
 * promotion et la liste des étudiants retenus : ceux qu'on omet ne sont
 * pas touchés. Tout geste est inscrit à l'historique.
 */
class FinDeCycleController extends Controller
{
    /* --- Référentiel des épreuves ----------------------------------------- */

    public function epreuves(): JsonResponse
    {
        return response()->json([
            'epreuves' => EpreuveFinDeCycle::query()->orderBy('rang')->orderBy('id')->get()
                ->map(fn (EpreuveFinDeCycle $e) => $this->epreuve($e))->values(),
        ]);
    }

    public function creerEpreuve(Request $request): JsonResponse
    {
        $donnees = $request->validate($this->reglesEpreuve());

        $epreuve = EpreuveFinDeCycle::create([
            'code' => $this->codeLibre($donnees['libelle']),
            'libelle' => $donnees['libelle'],
            'coefficient' => $donnees['coefficient'],
            'rang' => (int) EpreuveFinDeCycle::query()->max('rang') + 1,
            'actif' => true,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json(['epreuve' => $this->epreuve($epreuve)], 201);
    }

    /** Reprend l'intitulé et le coefficient. Le code, attribué, ne bouge pas. */
    public function modifierEpreuve(Request $request, int $epreuve): JsonResponse
    {
        $item = EpreuveFinDeCycle::find($epreuve);
        if (! $item) {
            return $this->refus(404, 'EPREUVE_INTROUVABLE', 'Épreuve introuvable.');
        }
        $donnees = $request->validate($this->reglesEpreuve());

        $item->update([...$donnees, 'updated_by' => $request->user()->id]);

        return response()->json(['epreuve' => $this->epreuve($item)]);
    }

    /** Retire une épreuve, ou la remet. Jamais de suppression. */
    public function activerEpreuve(Request $request, int $epreuve): JsonResponse
    {
        $item = EpreuveFinDeCycle::find($epreuve);
        if (! $item) {
            return $this->refus(404, 'EPREUVE_INTROUVABLE', 'Épreuve introuvable.');
        }
        $donnees = $request->validate(['actif' => ['required', 'boolean']]);
        $actif = (bool) $donnees['actif'];

        if (! $actif && $item->actif
            && EpreuveFinDeCycle::query()->where('actif', true)->whereKeyNot($item->id)->doesntExist()) {
            return $this->refus(409, 'REFERENTIEL_VIDE', 'La dernière épreuve active ne peut pas être retirée.');
        }

        $item->update(['actif' => $actif, 'updated_by' => $request->user()->id]);

        return response()->json(['epreuve' => $this->epreuve($item)]);
    }

    /* --- Promotions et notes ---------------------------------------------- */

    /** Rang du niveau qui passe le bulletin de fin de cycle : la 3e Année. */
    private const RANG_NIVEAU_FIN_DE_CYCLE = 3;

    /** Promotions de 3e Année proposées à la saisie, la plus récente d'abord. */
    public function promotions(): JsonResponse
    {
        $promotions = Promotion::query()->with('niveau')->withCount('inscriptions')
            ->whereHas('niveau', fn ($q) => $q->where('rang', self::RANG_NIVEAU_FIN_DE_CYCLE))
            ->get()
            ->sortByDesc(fn (Promotion $p) => $p->num_promotion ?? 0);

        return response()->json([
            'promotions' => $promotions->map(fn (Promotion $p) => $this->promotion($p))->values(),
        ]);
    }

    /** Les étudiants de la promotion et leurs notes aux épreuves actives. */
    public function notes(int $promotion): JsonResponse
    {
        $item = $this->promotionDeFinDeCycle($promotion);
        if (! $item) {
            return $this->refus(404, 'PROMOTION_INTROUVABLE', 'Promotion introuvable parmi celles de 3e Année.');
        }

        return response()->json($this->feuille($item));
    }

    /**
     * Saisit les notes d'une épreuve pour des étudiants de la promotion.
     * Une ligne sans note (`null`) ne touche rien : on n'efface pas une
     * note. Une note déjà portée se reprend, et prend le nom de qui la
     * reprend.
     */
    public function saisir(Request $request, int $promotion, int $epreuve): JsonResponse
    {
        $item = $this->promotionDeFinDeCycle($promotion);
        if (! $item) {
            return $this->refus(404, 'PROMOTION_INTROUVABLE', 'Promotion introuvable parmi celles de 3e Année.');
        }
        $cible = EpreuveFinDeCycle::find($epreuve);
        if (! $cible) {
            return $this->refus(404, 'EPREUVE_INTROUVABLE', 'Épreuve introuvable.');
        }
        if (! $cible->actif) {
            return $this->refus(409, 'EPREUVE_RETIREE', 'Cette épreuve est retirée : elle ne reçoit plus de notes.');
        }

        $donnees = $request->validate([
            'notes' => ['required', 'array', 'min:1'],
            'notes.*.id_etudiant' => ['required', 'integer', 'distinct'],
            'notes.*.note' => ['nullable', 'numeric', 'min:0', 'max:20', 'decimal:0,2'],
        ]);

        $inscrits = $this->etudiantsDe($item)->pluck('id')->all();
        $etrangers = collect($donnees['notes'])->pluck('id_etudiant')->diff($inscrits);
        if ($etrangers->isNotEmpty()) {
            return $this->refus(422, 'ETUDIANT_HORS_PROMOTION', "Un étudiant de la saisie n'appartient pas à cette promotion.");
        }

        $portees = collect($donnees['notes'])->filter(fn ($l) => $l['note'] !== null && $l['note'] !== '')->pluck('id_etudiant');
        $figes = $this->bulletinsDe($item)->keys()->intersect($portees);
        if ($figes->isNotEmpty()) {
            return $this->refus(409, 'NOTES_VALIDEES', "Les notes d'un étudiant de la saisie sont validées : renvoyez-les en saisie pour les reprendre.", $figes);
        }

        $auteur = $request->user()->id;
        DB::transaction(function () use ($donnees, $cible, $item, $auteur) {
            foreach ($donnees['notes'] as $ligne) {
                if ($ligne['note'] === null || $ligne['note'] === '') {
                    continue;
                }
                NoteFinDeCycle::updateOrCreate(
                    ['id_epreuve' => $cible->id, 'id_etudiant' => $ligne['id_etudiant'], 'id_promotion' => $item->id],
                    ['note' => round((float) $ligne['note'], 2), 'saisi_par' => $auteur],
                );
            }
        });

        return response()->json($this->feuille($item));
    }

    /* --- Validation, renvoi, publication ---------------------------------- */

    /**
     * Valide les notes des étudiants retenus : chaque bulletin reçoit ses
     * épreuves figées, sa moyenne et sa mention. Un étudiant déjà validé
     * ou publié est laissé tel quel. Tout ou rien : si un seul étudiant
     * retenu n'a pas toutes ses notes, rien n'est validé.
     */
    public function valider(Request $request, int $promotion): JsonResponse
    {
        $item = $this->promotionDeFinDeCycle($promotion);
        if (! $item) {
            return $this->refus(404, 'PROMOTION_INTROUVABLE', 'Promotion introuvable parmi celles de 3e Année.');
        }
        $retenus = $this->etudiantsRetenus($request, $item);
        if ($retenus instanceof JsonResponse) {
            return $retenus;
        }

        $epreuves = EpreuveFinDeCycle::query()->where('actif', true)->orderBy('rang')->orderBy('id')->get();
        $deja = $this->bulletinsDe($item);
        $aValider = $retenus->reject(fn (int $id) => $deja->has($id))->values();
        $notes = NoteFinDeCycle::query()->where('id_promotion', $item->id)
            ->whereIn('id_epreuve', $epreuves->pluck('id'))->whereIn('id_etudiant', $aValider)
            ->get()->groupBy('id_etudiant');

        $incomplets = $aValider->filter(fn (int $id) => ($notes[$id] ?? collect())->count() < $epreuves->count())->values();
        if ($incomplets->isNotEmpty()) {
            return $this->refus(409, 'NOTES_INCOMPLETES', "Un étudiant retenu n'a pas toutes ses notes : complétez-les ou retirez-le de la validation.", $incomplets);
        }

        $auteur = $request->user()->id;
        DB::transaction(function () use ($aValider, $item, $epreuves, $notes, $auteur) {
            foreach ($aValider as $id) {
                $parEpreuve = $notes[$id]->keyBy('id_epreuve');
                $somme = 0.0;
                $coefficients = 0;
                foreach ($epreuves as $epreuve) {
                    $somme += (float) $parEpreuve[$epreuve->id]->note * $epreuve->coefficient;
                    $coefficients += $epreuve->coefficient;
                }
                $moyenne = $coefficients > 0 ? round($somme / $coefficients, 2) : null;

                $bulletin = Bulletin::create([
                    'id_inscription' => $this->inscriptionDe($item, $id)->id,
                    'id_niveau' => $item->id_niveau,
                    'periode' => Bulletin::PERIODE_FIN_DE_CYCLE,
                    'moyenne' => $moyenne,
                    'mention' => MentionBulletinService::calculer($moyenne),
                    'statut' => Bulletin::STATUT_VALIDE,
                    'valide_par' => $auteur,
                    'valide_le' => now(),
                    'created_by' => $auteur,
                    'updated_by' => $auteur,
                ]);
                foreach ($epreuves as $epreuve) {
                    $note = $parEpreuve[$epreuve->id];
                    $bulletin->lignesFinDeCycle()->create([
                        'id_epreuve' => $epreuve->id,
                        'libelle' => $epreuve->libelle,
                        'coefficient' => $epreuve->coefficient,
                        'note' => round((float) $note->note, 2),
                        'rang' => $epreuve->rang,
                        'saisi_par' => $note->saisi_par,
                    ]);
                }
                $this->tracer($id, $item, HistoriqueBulletinFinDeCycle::VALIDATION, $moyenne, $auteur);
            }
        });

        return response()->json($this->feuille($item));
    }

    /**
     * Renvoie en saisie les étudiants retenus : leur bulletin validé est
     * retiré, leurs notes redeviennent modifiables. Un bulletin publié ne
     * se renvoie pas (tout ou rien) ; un étudiant non validé est laissé tel
     * quel.
     */
    public function renvoyer(Request $request, int $promotion): JsonResponse
    {
        $item = $this->promotionDeFinDeCycle($promotion);
        if (! $item) {
            return $this->refus(404, 'PROMOTION_INTROUVABLE', 'Promotion introuvable parmi celles de 3e Année.');
        }
        $retenus = $this->etudiantsRetenus($request, $item);
        if ($retenus instanceof JsonResponse) {
            return $retenus;
        }

        $bulletins = $this->bulletinsDe($item)->only($retenus->all());
        $publies = $bulletins->filter(fn (Bulletin $b) => $b->statut === Bulletin::STATUT_PUBLIE)->keys()->values();
        if ($publies->isNotEmpty()) {
            return $this->refus(409, 'BULLETIN_PUBLIE', "Le bulletin d'un étudiant retenu est déjà publié : il ne se renvoie plus en saisie.", $publies);
        }

        $auteur = $request->user()->id;
        DB::transaction(function () use ($bulletins, $item, $auteur) {
            foreach ($bulletins as $id => $bulletin) {
                $moyenne = $bulletin->moyenne;
                $bulletin->lignesFinDeCycle()->delete();
                $bulletin->delete();
                $this->tracer($id, $item, HistoriqueBulletinFinDeCycle::RENVOI, $moyenne, $auteur);
            }
        });

        return response()->json($this->feuille($item));
    }

    /**
     * Publie le bulletin des étudiants retenus : il paraît dans le portail
     * de l'étudiant. Tout ou rien : un étudiant retenu non validé bloque la
     * publication. Un bulletin déjà publié est laissé tel quel.
     */
    public function publier(Request $request, int $promotion): JsonResponse
    {
        $item = $this->promotionDeFinDeCycle($promotion);
        if (! $item) {
            return $this->refus(404, 'PROMOTION_INTROUVABLE', 'Promotion introuvable parmi celles de 3e Année.');
        }
        $retenus = $this->etudiantsRetenus($request, $item);
        if ($retenus instanceof JsonResponse) {
            return $retenus;
        }

        $bulletins = $this->bulletinsDe($item);
        $nonValides = $retenus->reject(fn (int $id) => $bulletins->has($id))->values();
        if ($nonValides->isNotEmpty()) {
            return $this->refus(409, 'BULLETIN_NON_VALIDE', "Les notes d'un étudiant retenu ne sont pas validées : validez-les ou retirez-le de la publication.", $nonValides);
        }

        $auteur = $request->user()->id;
        DB::transaction(function () use ($bulletins, $retenus, $item, $auteur) {
            foreach ($bulletins->only($retenus->all()) as $id => $bulletin) {
                if ($bulletin->statut === Bulletin::STATUT_PUBLIE) {
                    continue;
                }
                $bulletin->update([
                    'statut' => Bulletin::STATUT_PUBLIE,
                    'date_publication' => now(),
                    'publie_par' => $auteur,
                    'updated_by' => $auteur,
                ]);
                $this->tracer($id, $item, HistoriqueBulletinFinDeCycle::PUBLICATION, $bulletin->moyenne, $auteur);
            }
        });

        return response()->json($this->feuille($item));
    }

    /**
     * Les étudiants retenus pour un geste, tous de la promotion.
     *
     * @return Liste<int, int>|JsonResponse
     */
    private function etudiantsRetenus(Request $request, Promotion $promotion): Liste|JsonResponse
    {
        $donnees = $request->validate([
            'etudiants' => ['required', 'array', 'min:1'],
            'etudiants.*' => ['required', 'integer', 'distinct'],
        ]);
        $retenus = collect($donnees['etudiants'])->map(fn ($id) => (int) $id)->values();
        $etrangers = $retenus->diff($this->etudiantsDe($promotion)->pluck('id'))->values();
        if ($etrangers->isNotEmpty()) {
            return $this->refus(422, 'ETUDIANT_HORS_PROMOTION', "Un étudiant retenu n'appartient pas à cette promotion.", $etrangers);
        }

        return $retenus;
    }

    /**
     * Les bulletins de fin de cycle de la promotion, par étudiant.
     *
     * @return Liste<int, Bulletin>
     */
    private function bulletinsDe(Promotion $promotion): Liste
    {
        return Bulletin::query()->with(['inscription', 'valideur', 'publieur'])
            ->where('periode', Bulletin::PERIODE_FIN_DE_CYCLE)
            ->whereHas('inscription', fn ($q) => $q->where('id_promotion', $promotion->id))
            ->get()->keyBy(fn (Bulletin $b) => $b->inscription->id_etudiant)->toBase();
    }

    /** L'inscription la plus récente de l'étudiant dans la promotion. */
    private function inscriptionDe(Promotion $promotion, int $etudiant): Inscription
    {
        return Inscription::query()->where('id_promotion', $promotion->id)->where('id_etudiant', $etudiant)
            ->orderByDesc('id')->firstOrFail();
    }

    private function tracer(int $etudiant, Promotion $promotion, string $action, mixed $moyenne, int $auteur): void
    {
        HistoriqueBulletinFinDeCycle::create([
            'id_etudiant' => $etudiant,
            'id_promotion' => $promotion->id,
            'action' => $action,
            'moyenne' => $moyenne,
            'par' => $auteur,
        ]);
    }

    /* --- Présentation ------------------------------------------------------ */

    private function feuille(Promotion $promotion): array
    {
        $epreuves = EpreuveFinDeCycle::query()->where('actif', true)->orderBy('rang')->orderBy('id')->get();
        $etudiants = $this->etudiantsDe($promotion);
        $notes = NoteFinDeCycle::query()->with('auteur')
            ->where('id_promotion', $promotion->id)
            ->whereIn('id_epreuve', $epreuves->pluck('id'))
            ->get()->groupBy('id_etudiant');
        $bulletins = $this->bulletinsDe($promotion);

        return [
            'promotion' => $this->promotion($promotion),
            'epreuves' => $epreuves->map(fn (EpreuveFinDeCycle $e) => $this->epreuve($e))->values(),
            'etudiants' => $etudiants->map(fn (Etudiant $etudiant) => [
                'id' => $etudiant->id,
                'matricule' => $etudiant->matricule,
                'nom' => $etudiant->nom,
                'prenoms' => $etudiant->prenoms,
                'notes' => ($notes[$etudiant->id] ?? collect())->map(fn (NoteFinDeCycle $n) => [
                    'id_epreuve' => $n->id_epreuve,
                    'note' => round((float) $n->note, 2),
                    'saisi_par' => $this->personne($n->auteur),
                    'saisi_le' => $n->updated_at?->toIso8601String(),
                ])->values(),
                'bulletin' => $this->bulletin($bulletins->get($etudiant->id)),
            ])->values(),
        ];
    }

    /** La promotion, si elle est en 3e Année ; une autre n'a pas de fin de cycle. */
    private function promotionDeFinDeCycle(int $promotion): ?Promotion
    {
        return Promotion::with('niveau')->withCount('inscriptions')
            ->whereHas('niveau', fn ($q) => $q->where('rang', self::RANG_NIVEAU_FIN_DE_CYCLE))
            ->find($promotion);
    }

    /** @return Collection<int, Etudiant> */
    private function etudiantsDe(Promotion $promotion): Collection
    {
        return Etudiant::query()
            ->whereHas('inscriptions', fn ($q) => $q->where('id_promotion', $promotion->id))
            ->orderBy('nom')->orderBy('prenoms')->get();
    }

    /** L'état du bulletin de l'étudiant ; `null` : notes en saisie. */
    private function bulletin(?Bulletin $bulletin): ?array
    {
        if (! $bulletin) {
            return null;
        }
        $moyenne = $bulletin->moyenne !== null ? (float) $bulletin->moyenne : null;

        return [
            'id' => $bulletin->id,
            'statut' => $bulletin->statut === Bulletin::STATUT_PUBLIE ? 'publie' : 'valide',
            'moyenne' => $moyenne,
            'mention' => MentionBulletinService::afficher($bulletin->mention, $moyenne),
            'valide_par' => $this->personne($bulletin->valideur),
            'valide_le' => $bulletin->valide_le?->toIso8601String(),
            'publie_par' => $this->personne($bulletin->publieur),
            'publie_le' => $bulletin->date_publication?->toIso8601String(),
        ];
    }

    private function epreuve(EpreuveFinDeCycle $epreuve): array
    {
        return $epreuve->only(['id', 'code', 'libelle', 'coefficient', 'rang', 'actif']);
    }

    private function promotion(Promotion $promotion): array
    {
        return [
            'id' => $promotion->id,
            'code' => $promotion->code,
            'num_promotion' => $promotion->num_promotion,
            'niveau' => $promotion->niveau
                ? ['id' => $promotion->niveau->id, 'libelle' => $promotion->niveau->libelle, 'rang' => $promotion->niveau->rang]
                : null,
            'effectif' => (int) ($promotion->inscriptions_count ?? 0),
        ];
    }

    private function personne(?User $utilisateur): ?array
    {
        return $utilisateur ? ['id' => $utilisateur->id, 'nom' => $utilisateur->nom, 'prenoms' => $utilisateur->prenoms] : null;
    }

    private function reglesEpreuve(): array
    {
        return [
            'libelle' => ['required', 'string', 'min:3', 'max:120'],
            'coefficient' => ['required', 'integer', 'min:'.EpreuveFinDeCycle::COEFFICIENT_MIN, 'max:'.EpreuveFinDeCycle::COEFFICIENT_MAX],
        ];
    }

    /** Code attribué par le service, tiré du libellé, rendu unique. */
    private function codeLibre(string $libelle): string
    {
        $base = Str::limit(Str::slug($libelle), 50, '') ?: 'epreuve';
        $code = $base;
        for ($n = 2; EpreuveFinDeCycle::query()->where('code', $code)->exists(); $n++) {
            $code = "{$base}-{$n}";
        }

        return $code;
    }

    /** @param  Liste<int, int>|null  $etudiants  les étudiants en cause, servis avec le refus */
    private function refus(int $statut, string $code, string $message, ?Liste $etudiants = null): JsonResponse
    {
        $corps = ['code' => $code, 'message' => $message];
        if ($etudiants !== null) {
            $corps['etudiants'] = $etudiants->values()->all();
        }

        return response()->json($corps, $statut);
    }
}
