<?php

namespace App\Services;

use App\Models\AnneeAcademique;
use App\Models\CalendrierAcademique;
use App\Models\Creneau;
use Illuminate\Validation\ValidationException;

class CalendrierAcademiqueService
{
    public function valider(array $data, string $debut, string $fin): void
    {
        $errors = [];
        $dansAnnee = function (string $date, string $key) use ($debut, $fin, &$errors) {
            if ($date < $debut || $date > $fin) {
                $errors[$key][] = 'La date doit être comprise dans l’année académique.';
            }
        };
        $derniereActivite = $debut;
        foreach ($data['modules'] as $i => $module) {
            $dansAnnee($module['date_debut'], "modules.$i.date_debut");
            $dansAnnee($module['date_fin'], "modules.$i.date_fin");
            $derniereActivite = max($derniereActivite, $module['date_fin']);
            foreach (array_slice($data['modules'], 0, $i) as $autre) {
                if ($module['date_debut'] <= $autre['date_fin'] && $module['date_fin'] >= $autre['date_debut']) {
                    $errors["modules.$i.date_debut"][] = 'Deux modules ne peuvent pas se recouvrir (bornes incluses).';
                }
            }
            foreach ($module['examens'] as $j => $examen) {
                if ($examen['date_debut'] < $module['date_debut'] || $examen['date_fin'] > $module['date_fin']) {
                    $errors["modules.$i.examens.$j.date_debut"][] = 'Les examens doivent se tenir à l’intérieur du module.';
                }
            }
            foreach ($module['rattrapages'] as $j => $session) {
                $dansAnnee($session['date_debut'], "modules.$i.rattrapages.$j.date_debut");
                $dansAnnee($session['date_fin'], "modules.$i.rattrapages.$j.date_fin");
                if ($session['date_debut'] <= $module['date_fin']) {
                    $errors["modules.$i.rattrapages.$j.date_debut"][] = 'Le rattrapage doit commencer après la fin du module.';
                }
                $derniereActivite = max($derniereActivite, $session['date_fin']);
            }
        }
        foreach ($data['jours_feries'] as $i => $jour) {
            $dansAnnee($jour['date'], "jours_feries.$i.date");
        }
        foreach ($data['conges'] as $i => $conge) {
            $dansAnnee($conge['date_debut'], "conges.$i.date_debut");
            $dansAnnee($conge['date_fin'], "conges.$i.date_fin");
            $derniereActivite = max($derniereActivite, $conge['date_fin']);
        }
        if ($data['grandes_vacances'] !== null && $data['grandes_vacances']['date_debut'] <= $derniereActivite) {
            $errors['grandes_vacances.date_debut'][] = 'Les grandes vacances doivent suivre les modules, congés et derniers rattrapages.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** Le contrôleur détient le verrou de l’année pendant toute la transaction. */
    public function enregistrer(AnneeAcademique $annee, array $data, int $userId, bool $creation): CalendrierAcademique
    {
        $calendrier = $annee->calendrier()->first();
        abort_if($creation && $calendrier !== null, 409, 'Cette année possède déjà un calendrier. Utilisez PUT pour le modifier.');
        abort_if(! $creation && $calendrier === null, 404, 'Calendrier introuvable.');
        if ($calendrier && Creneau::whereIn('id_module_calendrier', $calendrier->modules()->select('id'))->exists()) {
            throw ValidationException::withMessages(['modules' => ['Supprimez ou réaffectez les créneaux avant de remplacer le calendrier.']]);
        }
        $this->valider($data, $annee->date_debut->toDateString(), $annee->date_fin->toDateString());
        $calendrier ??= $annee->calendrier()->create(['created_by' => $userId]);
        $calendrier->update(['updated_by' => $userId]);
        $calendrier->evenements()->delete();
        $calendrier->modules()->delete();
        foreach ($data['modules'] as $i => $item) {
            $module = $calendrier->modules()->create([
                'libelle' => $item['libelle'], 'ordre' => $i + 1,
                'date_debut' => $item['date_debut'], 'date_fin' => $item['date_fin'],
            ]);
            foreach (['examens' => 'examen', 'rattrapages' => 'rattrapage'] as $key => $type) {
                foreach ($item[$key] as $periode) {
                    $calendrier->evenements()->create([...$periode, 'type' => $type, 'id_module_calendrier' => $module->id]);
                }
            }
        }
        foreach ($data['jours_feries'] as $jour) {
            $calendrier->evenements()->create([
                'type' => 'jour_ferie', 'libelle' => $jour['libelle'],
                'date_debut' => $jour['date'], 'date_fin' => $jour['date'],
            ]);
        }
        foreach ($data['conges'] as $conge) {
            $calendrier->evenements()->create([...$conge, 'type' => 'conge']);
        }
        if ($data['grandes_vacances'] !== null) {
            $calendrier->evenements()->create([...$data['grandes_vacances'], 'type' => 'grandes_vacances']);
        }

        return $calendrier;
    }

    public function presenter(CalendrierAcademique $calendrier): array
    {
        $calendrier->load(['modules', 'evenements']);
        $periode = fn ($event) => ['date_debut' => $event->date_debut->toDateString(), 'date_fin' => $event->date_fin->toDateString()];
        $periodeAvecLibelle = fn ($event) => ['libelle' => $event->libelle, ...$periode($event)];
        $evenements = $calendrier->evenements;

        return [
            'id' => $calendrier->id,
            'id_annee_academique' => $calendrier->id_annee_academique,
            'modules' => $calendrier->modules->map(fn ($module) => [
                'libelle' => $module->libelle, ...$periode($module),
                'examens' => $evenements->where('id_module_calendrier', $module->id)->where('type', 'examen')->map($periodeAvecLibelle)->values()->all(),
                'rattrapages' => $evenements->where('id_module_calendrier', $module->id)->where('type', 'rattrapage')->map($periodeAvecLibelle)->values()->all(),
            ])->all(),
            'jours_feries' => $evenements->where('type', 'jour_ferie')->map(fn ($e) => ['libelle' => $e->libelle, 'date' => $e->date_debut->toDateString()])->values()->all(),
            'conges' => $evenements->where('type', 'conge')->map(fn ($e) => ['libelle' => $e->libelle, ...$periode($e)])->values()->all(),
            'grandes_vacances' => ($vacances = $evenements->firstWhere('type', 'grandes_vacances')) ? ['libelle' => $vacances->libelle, ...$periode($vacances)] : null,
            'created_by' => $calendrier->created_by,
            'updated_by' => $calendrier->updated_by,
            'updated_at' => $calendrier->updated_at,
        ];
    }
}
