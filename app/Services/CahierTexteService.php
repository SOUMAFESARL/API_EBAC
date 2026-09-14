<?php

namespace App\Services;

use App\Models\Creneau;
use App\Models\ModuleCalendrier;
use App\Models\SeanceCahierTexte;
use Carbon\CarbonImmutable;

class CahierTexteService
{
    public function synchroniserModule(ModuleCalendrier $module): void
    {
        SeanceCahierTexte::where('id_module_calendrier', $module->id)->where('statut', 'prevue')
            ->where('date_prevue', '>=', now()->toDateString())->delete();
        Creneau::where('id_module_calendrier', $module->id)->each(function (Creneau $creneau) use ($module) {
            $date = CarbonImmutable::parse($module->date_debut);
            $date = $date->addDays(($creneau->jour - $date->dayOfWeekIso + 7) % 7);
            $fin = CarbonImmutable::parse($module->date_fin);
            while ($date->lte($fin)) {
                $seance = SeanceCahierTexte::withTrashed()->firstOrNew(['id_creneau' => $creneau->id, 'date_prevue' => $date->toDateString()]);
                if (! $seance->exists || $seance->statut === 'prevue') {
                    $seance->fill([...$creneau->only(['id_module_calendrier', 'enseignant_id', 'id_niveau', 'id_matiere', 'id_cours', 'id_promotion', 'id_salle']),
                        'heure_debut_prevue' => $creneau->heure_debut, 'heure_fin_prevue' => $creneau->heure_fin,
                        'statut' => 'prevue', 'source' => 'planning', 'deleted_at' => null])->save();
                }
                $date = $date->addWeek();
            }
        });
    }

    public function estModifiable(SeanceCahierTexte $seance): bool
    {
        $echeance = CarbonImmutable::parse($seance->date_prevue->toDateString().' '.$seance->heure_debut_prevue)->addHours(48);

        return now()->lte($echeance);
    }
}
