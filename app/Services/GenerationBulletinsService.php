<?php

namespace App\Services;

use App\Models\Bulletin;
use App\Models\FeuilleNotes;
use App\Models\Inscription;
use App\Models\Matiere;
use Illuminate\Support\Facades\DB;

class GenerationBulletinsService
{
    public function synchroniser(FeuilleNotes $feuille, int $acteur, ?int $etudiant = null): void
    {
        DB::transaction(function () use ($feuille, $acteur, $etudiant) {
            // Lock inscriptions in a stable order, including when two sheets are validated together.
            $inscriptions = Inscription::where('id_promotion', $feuille->id_promotion)
                ->where('id_annee_academique', $feuille->id_annee_academique)
                ->when($etudiant !== null, fn ($q) => $q->where('id_etudiant', $etudiant))
                ->orderBy('id')->lockForUpdate()->get();
            $resultats = app(NotesValideesService::class)->moyennes(
                $feuille->id_promotion, $feuille->id_annee_academique,
                ['validee_secretariat', 'transmise_direction', 'validee_direction']
            )->where(fn ($q) => $q->where('f.statut', '!=', 'transmise_direction')
                ->orWhereExists(fn ($historique) => $historique->selectRaw('1')
                    ->from('historique_feuilles_notes as h')->whereColumn('h.id_feuille_notes', 'f.id')
                    ->where('h.statut_avant', 'validee_secretariat')->where('h.statut_apres', 'transmise_direction')
                    ->where('h.id', fn ($dernier) => $dernier->selectRaw('MAX(h2.id)')
                        ->from('historique_feuilles_notes as h2')->whereColumn('h2.id_feuille_notes', 'f.id'))))
                ->get()->groupBy('id_etudiant');
            $matieres = Matiere::whereIn('id', $resultats->flatten(1)->pluck('id_matiere'))->get()->keyBy('id');

            foreach ($inscriptions as $inscription) {
                $bulletin = Bulletin::where('id_inscription', $inscription->id)->where('periode', 'Annuel')->lockForUpdate()->first();
                // Publication freezes the official document; never overwrite it automatically.
                if ($bulletin && ($bulletin->date_publication !== null || ! in_array($bulletin->statut, ['Brouillon', 'Généré'], true))) {
                    continue;
                }
                $notes = ($resultats->get($inscription->id_etudiant) ?? collect())
                    ->filter(fn ($note) => $note->moyenne !== null && $matieres->has($note->id_matiere));
                if ($notes->isEmpty()) {
                    if ($bulletin?->statut === 'Généré') {
                        $bulletin->delete();
                    }

                    continue;
                }
                $bulletin ??= Bulletin::create(['id_inscription' => $inscription->id,
                    'periode' => 'Annuel', 'statut' => 'Généré', 'created_by' => $acteur]);
                if ($bulletin->statut === 'Brouillon') {
                    $bulletin->lignes()->delete();
                }
                $somme = 0.0;
                $coefficients = 0.0;
                foreach ($notes as $note) {
                    $coefficient = (float) $matieres[$note->id_matiere]->coefficient;
                    $moyenne = (float) $note->moyenne;
                    if ($coefficient > 0) {
                        $somme += $moyenne * $coefficient;
                        $coefficients += $coefficient;
                    }
                    $bulletin->lignes()->updateOrCreate(['id_matiere' => $note->id_matiere], [
                        'note' => round($moyenne, 2), 'coefficient' => $coefficient,
                        'moyenne_ponderee' => round($moyenne * $coefficient, 2),
                    ]);
                }
                $bulletin->lignes()->whereNotIn('id_matiere', $notes->pluck('id_matiere'))->delete();
                $moyenneGenerale = $coefficients > 0 ? round($somme / $coefficients, 2) : null;
                $bulletin->update(['statut' => 'Généré', 'mention' => MentionBulletinService::calculer($moyenneGenerale), 'rang' => null,
                    'decision' => null, 'fichier_chemin' => null,
                    'moyenne' => $moyenneGenerale,
                    'updated_by' => $acteur]);
            }
        });
    }
}
