<?php

namespace App\Services;

use App\Models\{Bulletin, Cours, Creneau, Matiere, Module, PublicationProgramme, SeanceCahierTexte, UniteEnseignement};
use Illuminate\Support\Facades\DB;

class SuppressionProgrammeService
{
    public function supprimerUnite(UniteEnseignement $unite, int $acteur): void
    {
        DB::transaction(function () use ($unite, $acteur) {
            $unite = UniteEnseignement::whereKey($unite->id)->lockForUpdate()->firstOrFail();
            foreach (Matiere::withTrashed()->where('unite_ensigne_id', $unite->id)->orderBy('id')->get() as $matiere) {
                $this->supprimerMatiere($matiere, $acteur);
            }
            $unite->update(['deleted_by' => $acteur]);
            $unite->delete();
        });
    }

    public function supprimerMatiere(Matiere $matiere, int $acteur): void
    {
        DB::transaction(function () use ($matiere, $acteur) {
            $matiere = Matiere::withTrashed()->whereKey($matiere->id)->lockForUpdate()->firstOrFail();
            $modules = Module::withTrashed()->where('id_matiere', $matiere->id)->pluck('id');
            $cours = Cours::withTrashed()->whereIn('id_module', $modules)->pluck('id');
            $seances = SeanceCahierTexte::withTrashed()->where('id_matiere', $matiere->id)
                ->orWhereIn('id_cours', $cours)->pluck('id');
            $feuilles = DB::table('feuilles_notes')->where('id_matiere', $matiere->id)
                ->orWhereIn('id_cours', $cours)->orWhereIn('id_seance', $seances)->pluck('id');
            $notes = DB::table('notes_cours')->whereIn('id_feuille_notes', $feuilles)->pluck('id');
            $corrections = DB::table('corrections_notes')->whereIn('id_note', $notes)->pluck('id');
            DB::table('traces_corrections_notes')->whereIn('id_correction', $corrections)->delete();
            DB::table('corrections_notes')->whereIn('id', $corrections)->delete();
            // Notes and workflow history are removed by the foreign-key cascades.
            DB::table('feuilles_notes')->whereIn('id', $feuilles)->delete();

            DB::table('programmations_rattrapage')->whereIn('id_seance_rattrapage', $seances)->delete();
            DB::table('cours_a_faire')->where('id_matiere', $matiere->id)
                ->orWhereIn('id_cours', $cours)->orWhereIn('id_seance', $seances)->delete();
            DB::table('feuilles_presence')->whereIn('id_seance', $seances)->delete();
            DB::table('affectations_enseignants')->where('id_matiere', $matiere->id)->orWhereIn('id_cours', $cours)->delete();

            $calendriers = DB::table('matiere_module_calendrier as pivot')
                ->join('modules_calendrier as module', 'module.id', '=', 'pivot.id_module_calendrier')
                ->where('pivot.id_matiere', $matiere->id)->pluck('module.id_calendrier')
                ->merge(SeanceCahierTexte::withTrashed()->whereIn('seances_cahier_texte.id', $seances)
                    ->join('modules_calendrier as module', 'module.id', '=', 'seances_cahier_texte.id_module_calendrier')->pluck('module.id_calendrier'))
                ->merge(Creneau::withTrashed()->where(fn ($q) => $q->where('id_matiere', $matiere->id)->orWhereIn('id_cours', $cours))
                    ->join('modules_calendrier as module', 'module.id', '=', 'creneaux.id_module_calendrier')->pluck('module.id_calendrier'));
            PublicationProgramme::whereIn('id_calendrier', $calendriers->unique())->where('statut', 'publie')
                ->update(['statut' => 'non_publie', 'date_retrait' => now(), 'retire_par' => $acteur]);
            DB::table('matiere_module_calendrier')->where('id_matiere', $matiere->id)->delete();

            foreach ([SeanceCahierTexte::whereIn('id', $seances),
                Creneau::where(fn ($q) => $q->where('id_matiere', $matiere->id)->orWhereIn('id_cours', $cours)),
                Cours::whereIn('id', $cours), Module::whereIn('id', $modules)] as $query) {
                $query->update(['deleted_by' => $acteur]);
                $query->delete();
            }

            $bulletins = Bulletin::whereNull('date_publication')->where('statut', '!=', 'Publié')
                ->whereHas('lignes', fn ($q) => $q->where('id_matiere', $matiere->id))->orderBy('id')->lockForUpdate()->get();
            foreach ($bulletins as $bulletin) {
                $inscription = $bulletin->inscription;
                // A changed average also invalidates the other unpublished ranks in this cohort.
                Bulletin::whereNull('date_publication')->where('statut', '!=', 'Publié')
                    ->where('periode', $bulletin->periode)
                    ->whereHas('inscription', fn ($q) => $q->where('id_promotion', $inscription->id_promotion)
                        ->where('id_annee_academique', $inscription->id_annee_academique))
                    ->update(['rang' => null, 'fichier_chemin' => null, 'updated_by' => $acteur]);
                $lignes = $bulletin->lignes()->where('id_matiere', $matiere->id)->pluck('id');
                DB::table('reclamations_notes')->whereIn('id_ligne_bulletin', $lignes)->delete();
                $bulletin->lignes()->whereIn('id', $lignes)->delete();
                $restantes = $bulletin->lignes()->get();
                if ($restantes->isEmpty()) {
                    $bulletin->delete();
                    continue;
                }
                $notees = $restantes->filter(fn ($ligne) => $ligne->note !== null && (float) $ligne->coefficient > 0);
                $coefficients = $notees->sum('coefficient');
                $moyenne = $coefficients > 0 ? round($notees->sum(fn ($ligne) => (float) $ligne->note * (float) $ligne->coefficient) / $coefficients, 2) : null;
                $bulletin->update(['moyenne' => $moyenne, 'mention' => MentionBulletinService::calculer($moyenne),
                    'rang' => null, 'decision' => null, 'fichier_chemin' => null, 'updated_by' => $acteur]);
            }
            $matiere->update(['deleted_by' => $acteur]);
            if (! $matiere->trashed()) {
                $matiere->delete();
            }
        });
    }
}
