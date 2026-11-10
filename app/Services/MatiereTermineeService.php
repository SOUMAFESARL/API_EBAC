<?php

namespace App\Services;

use App\Models\Matiere;
use App\Models\MatiereTerminee;
use App\Models\Promotion;
use Illuminate\Support\Collection;

// Matière non terminée pour sa promotion = « En cours » chez l'étudiant ; un niveau quitté vaut terminé.
class MatiereTermineeService
{
    public function __construct(private readonly PassageNiveauService $passages) {}

    /** @return Collection<int, MatiereTerminee> indexées par id de matière */
    public function terminees(int $promotionId): Collection
    {
        return MatiereTerminee::with('auteur:id,nom,prenoms')->where('id_promotion', $promotionId)->get()->keyBy('id_matiere');
    }

    public function enCours(Matiere $matiere, ?Promotion $promotion, ?Collection $terminees = null): bool
    {
        if (! $promotion || (int) $matiere->id_niveau !== (int) $promotion->id_niveau) {
            return false;
        }

        return ! ($terminees ?? $this->terminees($promotion->id))->has($matiere->id);
    }

    /** Promotions encore dans le cursus, avec leur niveau actuel. */
    public function promotions(): Collection
    {
        return Promotion::query()->with('niveau')->whereNotNull('id_niveau')->orderBy('num_promotion')->get()
            ->filter(fn (Promotion $promotion) => PassageNiveauService::enCours($promotion))
            ->map(fn (Promotion $promotion) => [
                ...$promotion->only(['id', 'code', 'num_promotion', 'annee_entree']),
                'niveau' => $promotion->niveau?->only(['id', 'code', 'libelle', 'rang']),
            ])->values();
    }

    public function etat(Promotion $promotion): array
    {
        $promotion->loadMissing('niveau');
        $terminees = $this->terminees($promotion->id);
        $matieres = Matiere::with('enseignant:id,nom,prenoms')->where('id_niveau', $promotion->id_niveau)
            ->where('active', true)->orderBy('libelle')->get();

        return [
            'promotion' => [...$promotion->only(['id', 'code', 'num_promotion']), 'niveau' => $promotion->niveau?->only(['id', 'code', 'libelle', 'rang'])],
            'annee_academique' => $this->passages->anneeSource($promotion)?->only(['id', 'libelle']),
            'matieres' => $matieres->map(fn (Matiere $matiere) => [
                ...$matiere->only(['id', 'code', 'libelle']),
                'enseignant' => $matiere->enseignant?->only(['id', 'nom', 'prenoms']),
                'terminee' => $this->presenter($terminees->get($matiere->id)),
            ])->values(),
        ];
    }

    public function presenter(?MatiereTerminee $terminee): ?array
    {
        return $terminee ? [
            'id' => $terminee->id,
            'le' => $terminee->created_at?->toIso8601String(),
            'par' => $terminee->auteur ? trim($terminee->auteur->nom.' '.$terminee->auteur->prenoms) : null,
        ] : null;
    }

    /** @return array{code: string, statut: int}|array{terminee: MatiereTerminee, creee: bool} */
    public function terminer(Matiere $matiere, Promotion $promotion, int $acteur): array
    {
        if (! PassageNiveauService::enCours($promotion)) {
            return ['code' => 'PROMOTION_CLOSE', 'statut' => 409];
        }
        if ((int) $matiere->id_niveau !== (int) $promotion->id_niveau) {
            return ['code' => 'NIVEAU_DIFFERENT', 'statut' => 422];
        }
        $terminee = MatiereTerminee::firstOrCreate(
            ['id_matiere' => $matiere->id, 'id_promotion' => $promotion->id],
            ['id_annee_academique' => $this->passages->anneeSource($promotion)?->id, 'terminee_par' => $acteur],
        );

        return ['terminee' => $terminee->load('auteur:id,nom,prenoms'), 'creee' => $terminee->wasRecentlyCreated];
    }

    /** @return array{code: string, statut: int}|null */
    public function rouvrir(MatiereTerminee $terminee): ?array
    {
        $terminee->loadMissing(['matiere', 'promotion']);
        if (! $terminee->promotion || ! $terminee->matiere || (int) $terminee->matiere->id_niveau !== (int) $terminee->promotion->id_niveau) {
            return ['code' => 'PASSAGE_EFFECTUE', 'statut' => 409];
        }
        $terminee->delete();

        return null;
    }
}
