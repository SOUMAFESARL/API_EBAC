<?php

namespace App\Services;

use App\Models\SeanceCahierTexte;
use Carbon\CarbonImmutable;

class CahierTexteService
{
    public function estModifiable(SeanceCahierTexte $seance): bool
    {
        $echeance = CarbonImmutable::parse($seance->date_prevue->toDateString().' '.$seance->heure_debut_prevue)->addHours(48);

        return now()->lte($echeance);
    }
}
