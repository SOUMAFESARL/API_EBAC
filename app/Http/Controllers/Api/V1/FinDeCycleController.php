<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EpreuveFinDeCycle;
use App\Models\Etudiant;
use App\Models\NoteFinDeCycle;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    /** Promotions proposées à la saisie, du niveau le plus avancé au premier. */
    public function promotions(): JsonResponse
    {
        $promotions = Promotion::query()->with('niveau')->withCount('inscriptions')->get()
            ->sortBy([
                fn ($a, $b) => ($b->niveau?->rang ?? 0) <=> ($a->niveau?->rang ?? 0),
                fn ($a, $b) => ($b->num_promotion ?? 0) <=> ($a->num_promotion ?? 0),
            ]);

        return response()->json([
            'promotions' => $promotions->map(fn (Promotion $p) => $this->promotion($p))->values(),
        ]);
    }

    /** Les étudiants de la promotion et leurs notes aux épreuves actives. */
    public function notes(int $promotion): JsonResponse
    {
        $item = Promotion::with('niveau')->withCount('inscriptions')->find($promotion);
        if (! $item) {
            return $this->refus(404, 'PROMOTION_INTROUVABLE', 'Promotion introuvable.');
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
        $item = Promotion::with('niveau')->withCount('inscriptions')->find($promotion);
        if (! $item) {
            return $this->refus(404, 'PROMOTION_INTROUVABLE', 'Promotion introuvable.');
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

    /* --- Présentation ------------------------------------------------------ */

    private function feuille(Promotion $promotion): array
    {
        $epreuves = EpreuveFinDeCycle::query()->where('actif', true)->orderBy('rang')->orderBy('id')->get();
        $etudiants = $this->etudiantsDe($promotion);
        $notes = NoteFinDeCycle::query()->with('auteur')
            ->where('id_promotion', $promotion->id)
            ->whereIn('id_epreuve', $epreuves->pluck('id'))
            ->get()->groupBy('id_etudiant');

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
            ])->values(),
        ];
    }

    /** @return Collection<int, Etudiant> */
    private function etudiantsDe(Promotion $promotion): Collection
    {
        return Etudiant::query()
            ->whereHas('inscriptions', fn ($q) => $q->where('id_promotion', $promotion->id))
            ->orderBy('nom')->orderBy('prenoms')->get();
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

    private function refus(int $statut, string $code, string $message): JsonResponse
    {
        return response()->json(['code' => $code, 'message' => $message], $statut);
    }
}
