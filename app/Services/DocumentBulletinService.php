<?php

namespace App\Services;

use App\Models\Bulletin;
use App\Models\CoursAFaire;
use App\Models\Etudiant;
use App\Models\LigneBulletinFinDeCycle;
use App\Models\Matiere;

/**
 * Le bulletin tel que le lit son document : l'étudiant dans son portail
 * (`MesBulletinsController`) et l'administration dans le registre des
 * bulletins (`RegistreBulletinsController`) lisent la même forme.
 *
 * Un bulletin de fin de cycle (09/11/2026) sert `fin_de_cycle` — ses
 * épreuves figées à la validation, leur total, la moyenne et sa mention —
 * à la place des matières.
 */
class DocumentBulletinService
{
    public function __construct(private BulletinQrCodeService $qr, private MatiereTermineeService $terminees) {}

    /** Ce que la liste montre d'un bulletin. */
    public function presenter(Bulletin $bulletin): array
    {
        $bulletin->loadMissing(['inscription.anneeAcademique', 'inscription.promotion.niveau', 'niveau']);
        $inscription = $bulletin->inscription;
        $annee = $inscription->anneeAcademique;
        $moyenne = $bulletin->moyenne !== null ? (float) $bulletin->moyenne : null;
        $niveau = $bulletin->niveau ?? $inscription->promotion?->niveau;

        return [
            ...$bulletin->only(['id', 'periode', 'mention', 'rang', 'decision', 'statut', 'date_publication']),
            'mention' => MentionBulletinService::afficher($bulletin->mention, $moyenne),
            'moyenne_generale' => $moyenne,
            'telechargement_pdf_url' => route('api.v1.etudiant.bulletins.telecharger', ['id' => $bulletin->id]),
            'annee_academique' => $annee ? [...$annee->only(['id', 'libelle', 'date_debut', 'date_fin']),
                'statut' => $annee->date_fin->lt(today()) ? 'cloturee' : 'en_cours'] : null,
            'promotion' => $inscription->promotion?->only(['id', 'code', 'num_promotion']),
            'niveau' => $niveau?->only(['id', 'code', 'libelle', 'rang']),
        ];
    }

    /** Le document complet : `{ etudiant, bulletin }`. */
    public function detail(Bulletin $bulletin): array
    {
        $bulletin->loadMissing(['inscription.etudiant', 'inscription.promotion', 'lignes.matiere.uniteEnseignement', 'lignesFinDeCycle']);
        $etudiant = $bulletin->inscription->etudiant;
        $finDeCycle = $bulletin->estFinDeCycle();

        $rattrapages = $finDeCycle || ! $etudiant ? collect() : CoursAFaire::where('id_etudiant', $etudiant->id)->where('statut', 'a_faire')
            ->whereHas('seance.moduleCalendrier.calendrier', fn ($q) => $q->where('id_annee_academique', $bulletin->inscription->id_annee_academique))
            ->with(['matiere', 'cours', 'seance', 'rattrapage'])->orderBy('id')->get();
        // « À reprendre » : un cours à faire de l'année non rattrapé, ou moyenne sous le seuil ; « À compléter » : sans note.
        $aReprendre = $rattrapages->countBy('id_matiere');
        // Toutes les matières actives du niveau figurent au bulletin, une seule fois chacune.
        $niveau = $bulletin->id_niveau ?? $bulletin->inscription->promotion?->id_niveau;
        $absentes = $finDeCycle || ! $niveau ? collect() : Matiere::query()->where('active', true)->where('id_niveau', $niveau)
            ->whereNotIn('id', $bulletin->lignes->pluck('id_matiere')->filter())
            ->with('uniteEnseignement')->orderBy('libelle')->get();
        $domaines = $bulletin->lignes->toBase()->map(fn ($ligne) => $ligne->matiere?->uniteEnseignement)
            ->concat($absentes->toBase()->map(fn (Matiere $matiere) => $matiere->uniteEnseignement))->filter()->keyBy('id');
        $promotion = $finDeCycle ? null : $bulletin->inscription->promotion;
        $terminees = $promotion ? $this->terminees->terminees($promotion->id) : collect();
        $enCours = fn (?Matiere $matiere) => $matiere && $this->terminees->enCours($matiere, $promotion, $terminees);
        $lignes = $bulletin->lignes->unique(fn ($ligne) => $ligne->id_matiere ?? 'ligne-'.$ligne->id)->values()->map(function ($ligne) use ($aReprendre, $enCours) {
            $note = $ligne->note !== null ? (float) $ligne->note : null;
            $statut = $note === null ? 'a_completer' : ($ligne->matiere && $note >= (float) $ligne->matiere->note_validation ? 'validee' : 'non_validee');
            $coursAFaire = $aReprendre->get($ligne->id_matiere, 0) > 0;
            $encore = $enCours($ligne->matiere);

            return [
                'id' => $ligne->id,
                'matiere' => $ligne->matiere?->only(['id', 'code', 'libelle', 'type', 'unite_ensigne_id']),
                'coefficient' => (float) $ligne->coefficient, 'note' => $note,
                'mention' => MentionBulletinService::calculer($note),
                'moyenne_ponderee' => $ligne->moyenne_ponderee !== null ? (float) $ligne->moyenne_ponderee : null,
                'appreciation' => $ligne->appreciation,
                'statut' => $statut,
                'en_cours' => $encore,
                'a_completer' => ! $encore && ! $coursAFaire && $note === null,
                'a_reprendre' => ! $encore && ($coursAFaire || $statut === 'non_validee'),
                'cours_a_reprendre' => $aReprendre->get($ligne->id_matiere, 0),
            ];
        })->concat($absentes->map(function (Matiere $matiere) use ($aReprendre, $enCours) {
            $encore = $enCours($matiere);

            return [
                'id' => null,
                'matiere' => $matiere->only(['id', 'code', 'libelle', 'type', 'unite_ensigne_id']),
                'coefficient' => (float) $matiere->coefficient, 'note' => null, 'mention' => null,
                'moyenne_ponderee' => null, 'appreciation' => null, 'statut' => 'a_completer',
                'en_cours' => $encore, 'a_completer' => ! $encore && ! $aReprendre->has($matiere->id),
                'a_reprendre' => ! $encore && $aReprendre->has($matiere->id),
                'cours_a_reprendre' => $aReprendre->get($matiere->id, 0),
            ];
        }));

        return [
            'etudiant' => $etudiant ? $this->presenterEtudiant($etudiant) : null,
            'bulletin' => [...$this->presenter($bulletin), 'matieres' => $lignes,
                'unites_enseignement' => $lignes->groupBy('matiere.unite_ensigne_id')
                    ->map(fn ($matieres) => [
                        'unite_ensigne_id' => $matieres->first()['matiere']['unite_ensigne_id'] ?? null,
                        'libelle' => $domaines->get($matieres->first()['matiere']['unite_ensigne_id'] ?? null)?->libelle,
                        'matieres' => $matieres->values(),
                    ])->values(),
                'fin_de_cycle' => $finDeCycle ? $this->finDeCycle($bulletin) : null,
                'qr_code' => $this->qr->presenter($bulletin),
                'recapitulatif_uv' => ['validees' => $lignes->where('statut', 'validee')->count(),
                    'non_validees' => $lignes->where('statut', 'non_validee')->count(), 'a_completer' => $lignes->where('statut', 'a_completer')->count()],
                'cours_a_faire' => $rattrapages->map(fn ($r) => [
                    ...$r->only(['id', 'statut', 'motif']),
                    'matiere' => $r->matiere?->only(['id', 'code', 'libelle', 'unite_ensigne_id']),
                    'cours' => $r->cours?->only(['id', 'code', 'libelle']),
                    'date_seance_manquee' => $r->seance?->date_prevue,
                    'statut_rattrapage' => $r->rattrapage?->statut === 'programme'
                        ? 'Inscrit au rattrapage du '.$r->rattrapage->date_prevue->format('d/m/Y') : 'À programmer',
                ]),
            ],
        ];
    }

    /** Les épreuves figées du bulletin de fin de cycle, leur total et la moyenne. */
    private function finDeCycle(Bulletin $bulletin): array
    {
        $moyenne = $bulletin->moyenne !== null ? (float) $bulletin->moyenne : null;

        return [
            'elements' => $bulletin->lignesFinDeCycle->map(fn (LigneBulletinFinDeCycle $ligne) => [
                'libelle' => $ligne->libelle,
                'coefficient' => $ligne->coefficient,
                'note' => round($ligne->note, 2),
                'mention' => MentionBulletinService::calculer($ligne->note),
            ])->values(),
            'total' => round($bulletin->lignesFinDeCycle->sum(fn (LigneBulletinFinDeCycle $l) => $l->note * $l->coefficient), 2),
            'moyenne_generale' => $moyenne,
            'mention' => MentionBulletinService::afficher($bulletin->mention, $moyenne),
        ];
    }

    public function presenterEtudiant(Etudiant $etudiant, string $formatDate = 'Y-m-d'): array
    {
        $etudiant->loadMissing(['civilite', 'eglise', 'dossier']);

        return [
            ...$etudiant->only(['id', 'matricule', 'nom', 'prenoms', 'lieu_naissance', 'nationalite',
                'email', 'telephone', 'adresse', 'statut_professionnel', 'situation_matrimonial',
                'nombre_enfant', 'statut', 'civilite_id', 'eglise_id', 'photo_identite_url']),
            'date_naissance' => $etudiant->date_naissance?->format($formatDate),
            'date_inscription' => $etudiant->date_inscription?->format($formatDate),
            'civilite' => $etudiant->civilite?->name,
            'eglise' => $etudiant->eglise?->only(['id', 'code', 'nom', 'denomination', 'region', 'district', 'ville_commune']),
            'dossier' => $etudiant->dossier ? [
                ...$etudiant->dossier->only(['id', 'numero_dossier', 'statut']),
                'date_ouverture' => $etudiant->dossier->date_ouverture?->format($formatDate),
            ] : null,
        ];
    }
}
