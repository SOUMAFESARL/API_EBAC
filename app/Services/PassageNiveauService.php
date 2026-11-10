<?php

namespace App\Services;

use App\Models\AnneeAcademique;
use App\Models\CoursAFaire;
use App\Models\Inscription;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\ParcoursAcademique;
use App\Models\PassageNiveau;
use App\Models\Promotion;
use App\Models\SeanceCahierTexte;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PassageNiveauService
{
    public const DECISION = 'Passage au niveau supérieur';

    /** Promotion encore dans le cursus : ni diplômée, ni archivée, ni clôturée. */
    public static function enCours(Promotion $promotion): bool
    {
        $statut = Str::lower(Str::ascii((string) $promotion->statut));

        return ! Str::startsWith($statut, ['diplom', 'archiv', 'clotur']);
    }

    /** Seul un départ (abandon, exclusion, archivage…) ne monte pas. */
    public static function suitLaFormation(?string $statut): bool
    {
        $statut = Str::lower(Str::ascii(trim((string) $statut)));

        return ! Str::startsWith($statut, ['depart', 'abandon', 'archiv', 'exclu', 'diplom', 'radie']);
    }

    /** La 4e Année clôt le cursus, même si un niveau de rang supérieur existe. */
    public static function derniereAnnee(Niveau $niveau): bool
    {
        $libelle = Str::lower(Str::ascii((string) $niveau->libelle));

        return (bool) preg_match('/^\s*4\s*(e|eme|ieme)?\b.*annee/', $libelle);
    }

    public function niveauSuivant(?Niveau $niveau): ?Niveau
    {
        if (! $niveau || self::derniereAnnee($niveau)) {
            return null;
        }

        return Niveau::query()->where('statut', 'Actif')->where('rang', '>', $niveau->rang)->orderBy('rang')->first();
    }

    /** Promotions en cours qui ont un niveau suivant : la dernière année n'en a pas. */
    public function promotionsEligibles(): Collection
    {
        return Promotion::query()->with('niveau')->orderBy('num_promotion')->get()
            ->filter(fn (Promotion $promotion) => self::enCours($promotion) && $this->niveauSuivant($promotion->niveau))
            ->map(function (Promotion $promotion) {
                $annee = $this->anneeSource($promotion);

                return [
                    'id' => $promotion->id,
                    'code' => $promotion->code,
                    'num_promotion' => $promotion->num_promotion,
                    'annee_entree' => $promotion->annee_entree,
                    'niveau_actuel' => $promotion->niveau?->only(['id', 'libelle', 'rang']),
                    'niveau_suivant' => $this->niveauSuivant($promotion->niveau)?->only(['id', 'libelle', 'rang']),
                    'effectif' => $annee ? Inscription::where('id_promotion', $promotion->id)->where('id_annee_academique', $annee->id)->count() : 0,
                ];
            })->values();
    }

    /** L'année que la promotion achève : celle de ses inscriptions les plus récentes. */
    public function anneeSource(Promotion $promotion): ?AnneeAcademique
    {
        $annee = AnneeAcademique::query()
            ->whereIn('id', Inscription::where('id_promotion', $promotion->id)->whereNotNull('id_annee_academique')->select('id_annee_academique'))
            ->orderByDesc('date_debut')->first();

        return $annee ?? AnneeAcademique::query()->where('active', true)->first();
    }

    public function anneeCible(?AnneeAcademique $source): ?AnneeAcademique
    {
        if (! $source) {
            return null;
        }

        return AnneeAcademique::query()->where('date_debut', '>', $source->date_debut->toDateString())->orderBy('date_debut')->first();
    }

    public function rapport(Promotion $promotion): array
    {
        $promotion->loadMissing('niveau');
        $niveau = $promotion->niveau;
        $suivant = $this->niveauSuivant($niveau);
        $source = $this->anneeSource($promotion);
        $cible = $this->anneeCible($source);

        $inscriptions = $source
            ? Inscription::with('etudiant')->where('id_promotion', $promotion->id)->where('id_annee_academique', $source->id)->get()
            : collect();
        $etudiants = $inscriptions->pluck('etudiant')->filter()->keyBy('id');

        $matieres = $niveau ? Matiere::with('enseignant:id,nom,prenoms')->where('id_niveau', $niveau->id)
            ->where('active', true)->orderBy('libelle')->get() : collect();
        $seances = $this->seances($promotion, $niveau, $source);

        $notes = $matieres->map(fn (Matiere $matiere) => [
            'matiere_id' => $matiere->id,
            'matiere_libelle' => $matiere->libelle,
            'enseignant' => $matiere->enseignant ? trim($matiere->enseignant->nom.' '.$matiere->enseignant->prenoms) : 'Enseignant non désigné',
            'validee' => $this->notesValidees($promotion, $source, $matiere->id),
        ])->values();

        $presents = $etudiants->isEmpty() || $seances->isEmpty() ? collect() : DB::table('presences as p')
            ->join('feuilles_presence as f', 'f.id', '=', 'p.id_feuille_presence')
            ->join('seances_cahier_texte as s', 's.id', '=', 'f.id_seance')
            ->whereIn('f.id_seance', $seances->keys())->where('f.statut', 'validee')->where('p.statut', 'present')
            ->whereIn('p.id_etudiant', $etudiants->keys())
            ->select('p.id_etudiant', 's.id_matiere')->distinct()->get()
            ->groupBy('id_etudiant')->map(fn ($lignes) => $lignes->pluck('id_matiere')->all());

        $aFaire = $etudiants->isEmpty() || $seances->isEmpty() ? collect() : CoursAFaire::with('cours:id,libelle')
            ->whereIn('id_etudiant', $etudiants->keys())->whereIn('id_seance', $seances->keys())
            ->where('statut', 'a_faire')->get()->groupBy('id_etudiant');

        $lignes = $inscriptions->filter(fn ($i) => $i->etudiant)->map(function (Inscription $inscription) use ($matieres, $seances, $presents, $aFaire) {
            $etudiant = $inscription->etudiant;
            $dus = $aFaire->get($etudiant->id, collect());
            $suivies = $presents->get($etudiant->id, []);
            $compte = ['validees' => 0, 'partielles' => 0, 'non_commencees' => 0];
            foreach ($matieres as $matiere) {
                $commencee = in_array($matiere->id, $suivies);
                $reste = $dus->where('id_matiere', $matiere->id)->isNotEmpty();
                $compte[match (true) { ! $commencee => 'non_commencees', $reste => 'partielles', default => 'validees' }]++;
            }

            return [
                'etudiant_id' => $etudiant->id,
                'matricule' => $etudiant->matricule,
                'nom' => $etudiant->nom,
                'prenoms' => $etudiant->prenoms,
                'matieres_validees' => $compte['validees'],
                'matieres_partielles' => $compte['partielles'],
                'matieres_non_commencees' => $compte['non_commencees'],
                'cours_a_faire' => $dus->map(fn (CoursAFaire $cours) => [
                    'cours_id' => $cours->id,
                    'cours_libelle' => $cours->cours?->libelle ?? 'Séance du '.$seances->get($cours->id_seance)?->date_prevue?->format('d/m/Y'),
                    'matiere_libelle' => $matieres->firstWhere('id', $cours->id_matiere)?->libelle ?? '',
                ])->values(),
                'suit_la_promotion' => self::suitLaFormation($inscription->statut) && self::suitLaFormation($etudiant->statut),
            ];
        })->sortBy(['nom', 'prenoms'])->values();

        $obstacles = [];
        if ($notes->contains('validee', false)) {
            $obstacles[] = 'notes-en-attente';
        }
        if (! $suivant) {
            $obstacles[] = 'dernier-niveau';
        }
        if (! self::enCours($promotion)) {
            $obstacles[] = 'promotion-close';
        }
        if (! $lignes->contains('suit_la_promotion', true)) {
            $obstacles[] = 'promotion-vide';
        }
        if ($suivant && ! $cible) {
            $obstacles[] = 'annee-suivante-absente';
        }

        return [
            'genere_le' => (int) floor(microtime(true) * 1000),
            'promotion_id' => $promotion->id,
            'num_promotion' => $promotion->num_promotion,
            'annee_academique' => $source?->libelle,
            'annee_suivante' => $cible?->libelle,
            'niveau_actuel_id' => $niveau?->id,
            'niveau_actuel_libelle' => $niveau?->libelle,
            'niveau_suivant_id' => $suivant?->id,
            'niveau_suivant_libelle' => $suivant?->libelle,
            'notes_de_l_annee' => $notes,
            'obstacles' => $obstacles,
            'etudiants' => $lignes,
        ];
    }

    /**
     * @return array{code: string, statut: int}|array{bilan: array}
     */
    public function executer(int $promotionId, int $rapportVuLe, bool $malgreNotes, int $acteur): array
    {
        return DB::transaction(function () use ($promotionId, $rapportVuLe, $malgreNotes, $acteur) {
            $promotion = Promotion::query()->whereKey($promotionId)->lockForUpdate()->first();
            if (! $promotion) {
                return ['code' => 'PROMOTION_INTROUVABLE', 'statut' => 404];
            }
            $vuLe = intdiv($rapportVuLe, 1000);
            if (PassageNiveau::where('id_promotion', $promotion->id)->where('created_at', '>=', date('Y-m-d H:i:s', $vuLe))->exists()) {
                return ['code' => 'PASSAGE_DEJA_EFFECTUE', 'statut' => 409];
            }

            $rapport = $this->rapport($promotion);
            $refus = match (true) {
                in_array('promotion-close', $rapport['obstacles']) => 'PROMOTION_CLOSE',
                in_array('dernier-niveau', $rapport['obstacles']) => 'DERNIER_NIVEAU',
                in_array('promotion-vide', $rapport['obstacles']) => 'PROMOTION_INTROUVABLE',
                in_array('annee-suivante-absente', $rapport['obstacles']) => 'ANNEE_SUIVANTE_ABSENTE',
                default => null,
            };
            if ($refus) {
                return ['code' => $refus, 'statut' => $refus === 'PROMOTION_INTROUVABLE' ? 404 : 409];
            }
            if ($this->modifieDepuis($promotion, $rapport, $vuLe)) {
                return ['code' => 'RAPPORT_PERIME', 'statut' => 409];
            }
            if (in_array('notes-en-attente', $rapport['obstacles']) && ! $malgreNotes) {
                return ['code' => 'NOTES_EN_ATTENTE', 'statut' => 409];
            }

            $source = $this->anneeSource($promotion);
            $cible = $this->anneeCible($source);
            $niveauSource = $promotion->niveau;
            $suivant = $this->niveauSuivant($niveauSource);
            $avancent = collect($rapport['etudiants'])->where('suit_la_promotion', true);
            $maintenant = now();

            foreach ($avancent as $ligne) {
                Inscription::where('id_promotion', $promotion->id)->where('id_annee_academique', $source->id)
                    ->where('id_etudiant', $ligne['etudiant_id'])->first()
                    ?->update(['decision_passage' => self::DECISION, 'date_decision' => $maintenant]);
                Inscription::firstOrCreate(
                    ['id_etudiant' => $ligne['etudiant_id'], 'id_annee_academique' => $cible->id],
                    ['id_promotion' => $promotion->id, 'date_inscription' => $maintenant->toDateString(),
                        'statut' => 'En formation', 'created_by' => $acteur],
                );
                ParcoursAcademique::updateOrCreate(
                    ['id_etudiant' => $ligne['etudiant_id'], 'id_annee_academique' => $source->id, 'type_parcours' => 'Interne'],
                    ['id_niveau' => $niveauSource->id, 'id_promotion' => $promotion->id, 'statut' => 'Terminé',
                        'decision' => self::DECISION, 'date_debut' => $source->date_debut, 'date_fin' => $source->date_fin, 'updated_by' => $acteur],
                );
                ParcoursAcademique::updateOrCreate(
                    ['id_etudiant' => $ligne['etudiant_id'], 'id_annee_academique' => $cible->id, 'type_parcours' => 'Interne'],
                    ['id_niveau' => $suivant->id, 'id_promotion' => $promotion->id, 'statut' => 'En cours',
                        'date_debut' => $cible->date_debut, 'created_by' => $acteur, 'updated_by' => $acteur],
                );
            }

            $promotion->update(['id_niveau' => $suivant->id, 'updated_by' => $acteur]);
            $reportes = $avancent->sum(fn ($ligne) => count($ligne['cours_a_faire']));
            $nonAvances = count($rapport['etudiants']) - $avancent->count();

            PassageNiveau::create([
                'id_promotion' => $promotion->id, 'id_niveau_source' => $niveauSource->id, 'id_niveau_cible' => $suivant->id,
                'id_annee_source' => $source->id, 'id_annee_cible' => $cible->id,
                'etudiants_avances' => $avancent->count(), 'etudiants_non_avances' => $nonAvances,
                'cours_a_faire_reportes' => $reportes, 'malgre_notes_en_attente' => $malgreNotes, 'effectue_par' => $acteur,
            ]);

            return ['bilan' => [
                'promotion_id' => $promotion->id,
                'niveau_atteint_id' => $suivant->id,
                'niveau_atteint_libelle' => $suivant->libelle,
                'annee_academique' => $cible->libelle,
                'etudiants_avances' => $avancent->count(),
                'etudiants_non_avances' => $nonAvances,
                'cours_a_faire_reportes' => $reportes,
            ]];
        });
    }

    /** Séances de l'année que la promotion achève, au niveau qu'elle quitte. */
    private function seances(Promotion $promotion, ?Niveau $niveau, ?AnneeAcademique $annee): Collection
    {
        if (! $niveau) {
            return collect();
        }

        return SeanceCahierTexte::query()->where('id_niveau', $niveau->id)
            ->where(fn ($q) => $q->where('id_promotion', $promotion->id)
                ->when($annee?->date_debut && $annee?->date_fin, fn ($q) => $q->orWhere(fn ($q) => $q->whereNull('id_promotion')
                    ->whereBetween('date_prevue', [$annee->date_debut->toDateString(), $annee->date_fin->toDateString()]))))
            ->get(['id', 'id_matiere', 'date_prevue'])->keyBy('id');
    }

    /** Toutes les feuilles de la matière sont validées par la Direction (au moins une). */
    private function notesValidees(Promotion $promotion, ?AnneeAcademique $annee, int $matiere): bool
    {
        if (! $annee) {
            return false;
        }
        $statuts = DB::table('feuilles_notes as f')->leftJoin('cours as c', 'c.id', '=', 'f.id_cours')
            ->leftJoin('modules as m', 'm.id', '=', 'c.id_module')
            ->where('f.id_promotion', $promotion->id)->where('f.id_annee_academique', $annee->id)
            ->whereRaw('COALESCE(m.id_matiere, f.id_matiere) = ?', [$matiere])->pluck('f.statut');

        return $statuts->isNotEmpty() && $statuts->every(fn ($statut) => $statut === 'validee_direction');
    }

    /** Une écriture postérieure au rapport relu : promotion, inscriptions, notes, cours dus. */
    private function modifieDepuis(Promotion $promotion, array $rapport, int $vuLe): bool
    {
        $seuil = date('Y-m-d H:i:s', $vuLe + 1);
        $etudiants = collect($rapport['etudiants'])->pluck('etudiant_id');

        return ($promotion->updated_at && $promotion->updated_at->getTimestamp() > $vuLe)
            || Inscription::where('id_promotion', $promotion->id)->where('updated_at', '>=', $seuil)->exists()
            || DB::table('feuilles_notes')->where('id_promotion', $promotion->id)->where('updated_at', '>=', $seuil)->exists()
            || CoursAFaire::whereIn('id_etudiant', $etudiants)->where('updated_at', '>=', $seuil)->exists();
    }
}
