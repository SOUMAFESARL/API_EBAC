<?php

namespace App\Services;

use App\Models\AnneeAcademique;
use App\Models\Cours;
use App\Models\Creneau;
use App\Models\Matiere;
use App\Models\ModuleCalendrier;
use App\Models\Niveau;
use App\Models\Promotion;
use App\Models\Salle;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class CreneauService
{
    public function enregistrer(array $data, int $userId, ?Creneau $creneau = null): Creneau
    {
        $data = [...($creneau?->only(['id_module_calendrier', 'id_niveau', 'id_matiere', 'id_cours', 'id_promotion', 'enseignant_id', 'id_salle', 'jour', 'heure_debut', 'heure_fin']) ?? []), ...$data];
        $data['heure_debut'] = substr($data['heure_debut'], 0, 5);
        $data['heure_fin'] = substr($data['heure_fin'], 0, 5);
        $refuser = fn ($key, $message) => throw ValidationException::withMessages([$key => [$message]]);
        if ($data['heure_fin'] <= $data['heure_debut']) {
            $refuser('heure_fin', 'La fin doit être strictement après le début, le même jour.');
        }
        $module = ModuleCalendrier::find($data['id_module_calendrier']);
        if (! $module) {
            $refuser('id_module_calendrier', 'Module du calendrier introuvable.');
        }
        $annee = AnneeAcademique::query()->lockForUpdate()->find($module->calendrier->id_annee_academique);
        if (! $annee) {
            $refuser('id_module_calendrier', 'Année académique indisponible.');
        }
        $module = ModuleCalendrier::find($data['id_module_calendrier']);
        if (! $module) {
            $refuser('id_module_calendrier', 'Le calendrier a été modifié. Rechargez les modules.');
        }
        // Les verrous de ressources sérialisent également les réservations entre années.
        $niveau = Niveau::query()->lockForUpdate()->find($data['id_niveau']);
        if (! $niveau) {
            $refuser('id_niveau', 'Niveau introuvable.');
        }
        $enseignant = User::query()->lockForUpdate()->find($data['enseignant_id']);
        if (! $enseignant || ! $enseignant->is_active || $enseignant->statut !== 'Actif' || $enseignant->role?->code !== 'ENSEIGNANT') {
            $refuser('enseignant_id', 'Sélectionnez un enseignant actif.');
        }
        $salle = Salle::query()->lockForUpdate()->find($data['id_salle']);
        if (! $salle || $salle->statut !== 'Actif') {
            $refuser('id_salle', 'Sélectionnez une salle active.');
        }
        $matiere = Matiere::find($data['id_matiere']);
        if (! $matiere || ! $matiere->active || $matiere->id_niveau != $niveau->id) {
            $refuser('id_matiere', 'La matière doit être active et appartenir au niveau sélectionné.');
        }
        if ($data['id_cours'] ?? null) {
            $cours = Cours::find($data['id_cours']);
            if (! $cours || ! $cours->actif || $cours->module?->id_matiere != $matiere->id) {
                $refuser('id_cours', 'Le cours doit être actif et appartenir à la matière sélectionnée.');
            }
        }
        if ($data['id_promotion'] ?? null) {
            $promotion = Promotion::find($data['id_promotion']);
            if (! $promotion || $promotion->id_niveau != $niveau->id) {
                $refuser('id_promotion', 'La promotion doit appartenir au niveau sélectionné.');
            }
        }
        $concurrents = Creneau::query()->where('jour', $data['jour'])
            ->where('heure_debut', '<', $data['heure_fin'].':00')->where('heure_fin', '>', $data['heure_debut'].':00')
            ->when($creneau, fn ($q) => $q->whereKeyNot($creneau->id))
            ->where(fn ($q) => $q->where('id_niveau', $niveau->id)->orWhere('enseignant_id', $enseignant->id)->orWhere('id_salle', $salle->id))
            ->whereHas('moduleCalendrier', fn ($q) => $q->where('date_debut', '<=', $module->date_fin)->where('date_fin', '>=', $module->date_debut)
                ->whereHas('calendrier.anneeAcademique'))->with('moduleCalendrier')->lockForUpdate()->get();
        $errors = [];
        foreach ($concurrents as $autre) {
            $debut = max($module->date_debut->toDateString(), $autre->moduleCalendrier->date_debut->toDateString());
            $fin = min($module->date_fin->toDateString(), $autre->moduleCalendrier->date_fin->toDateString());
            $date = CarbonImmutable::parse($debut);
            $date = $date->addDays(((int) $data['jour'] - $date->dayOfWeekIso + 7) % 7);
            if ($date->toDateString() > $fin) {
                continue;
            }
            foreach (['id_niveau' => 'niveau', 'enseignant_id' => 'enseignant', 'id_salle' => 'salle'] as $key => $label) {
                if ($data[$key] == $autre->$key) {
                    $errors[$key][] = "Conflit avec le créneau {$autre->id} : même $label, jour et horaires qui se chevauchent.";
                }
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        $data['heure_debut'] .= ':00';
        $data['heure_fin'] .= ':00';
        if ($creneau) {
            $creneau->update([...$data, 'updated_by' => $userId]);
        } else {
            $creneau = Creneau::create([...$data, 'created_by' => $userId]);
        }

        return $creneau->fresh();
    }

    public function presenter(Creneau $creneau): array
    {
        $creneau->loadMissing(['moduleCalendrier.calendrier', 'niveau', 'matiere', 'cours', 'promotion', 'enseignant', 'salle']);
        $minutes = fn ($time) => (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);

        return [...$creneau->attributesToArray(),
            'heure_debut' => substr($creneau->heure_debut, 0, 5), 'heure_fin' => substr($creneau->heure_fin, 0, 5),
            'duree_minutes' => $minutes($creneau->heure_fin) - $minutes($creneau->heure_debut),
            'module_calendrier' => $creneau->moduleCalendrier?->only(['id', 'libelle', 'date_debut', 'date_fin']),
            'id_annee_academique' => $creneau->moduleCalendrier?->calendrier?->id_annee_academique,
            'niveau' => $creneau->niveau?->only(['id', 'libelle']), 'matiere' => $creneau->matiere?->only(['id', 'libelle']),
            'cours' => $creneau->cours?->only(['id', 'libelle']), 'promotion' => $creneau->promotion?->only(['id', 'code']),
            'enseignant' => $creneau->enseignant?->only(['id', 'nom', 'prenoms']), 'salle' => $creneau->salle?->only(['id', 'nom', 'code']),
        ];
    }
}
