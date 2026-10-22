<?php

namespace App\Console\Commands;

use App\Models\Bulletin;
use App\Models\Etudiant;
use App\Models\FeuilleNotes;
use App\Models\Inscription;
use App\Services\GenerationBulletinsService;
use Illuminate\Console\Command;

class GenererBulletins extends Command
{
    protected $signature = 'bulletins:generer {--etudiant= : Identifiant de l’étudiant à reprendre}';

    protected $description = 'Générer les bulletins à partir des notes déjà validées pour un étudiant';

    public function handle(GenerationBulletinsService $service): int
    {
        $id = filter_var($this->option('etudiant'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false || ! Etudiant::whereKey($id)->exists()) {
            $this->error('Indiquez un étudiant existant avec --etudiant=ID.');

            return self::FAILURE;
        }
        $inscriptions = Inscription::where('id_etudiant', $id)->get();
        if ($inscriptions->isEmpty()) {
            $this->warn('Aucune inscription pour cet étudiant. Aucun bulletin généré.');

            return self::SUCCESS;
        }
        foreach ($inscriptions as $inscription) {
            $feuille = FeuilleNotes::where('id_promotion', $inscription->id_promotion)
                ->where('id_annee_academique', $inscription->id_annee_academique)
                ->whereIn('statut', ['validee_secretariat', 'transmise_direction', 'validee_direction'])
                ->orderBy('id')->first();
            if ($feuille) {
                $service->synchroniser($feuille, $feuille->updated_by, $id);
            }
        }
        $total = Bulletin::whereIn('id_inscription', $inscriptions->pluck('id'))->where('statut', 'Généré')->count();
        $this->info("Reprise terminée : {$total} bulletin(s) au statut Généré pour l’étudiant {$id}.");
        if ($total === 0) {
            $this->warn('Aucun bulletin généré : vérifier les notes validées et les bulletins déjà publiés ou retirés.');
        }

        return self::SUCCESS;
    }
}
