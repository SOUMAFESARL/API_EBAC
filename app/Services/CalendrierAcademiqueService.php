<?php

namespace App\Services;

use App\Models\AnneeAcademique;
use App\Models\CalendrierAcademique;
use App\Models\Creneau;
use App\Models\ModuleCalendrier;
use App\Models\PublicationProgramme;
use App\Models\SeanceCahierTexte;
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
        $this->valider($data, $annee->date_debut->toDateString(), $annee->date_fin->toDateString());
        $calendrier ??= $annee->calendrier()->create(['created_by' => $userId]);
        $existants = $calendrier->modules()->lockForUpdate()->get()->keyBy('id');
        $reserves = collect($data['modules'])->pluck('id')->filter()->all();
        $modules = [];
        foreach ($data['modules'] as $i => $item) {
            $module = null;
            if (isset($item['id'])) {
                $module = $existants->get($item['id']);
                if (! $module) {
                    throw ValidationException::withMessages(["modules.$i.id" => ['Ce module ne fait pas partie de ce calendrier.']]);
                }
            } elseif (! array_key_exists('id', $item)) {
                // Compatibilité avec les formulaires qui ne transmettaient pas les identifiants.
                $candidats = $existants->except($reserves)->filter(fn ($m) => $m->libelle === $item['libelle']);
                if ($candidats->count() !== 1) {
                    $candidats = $existants->except($reserves)->filter(fn ($m) => $m->date_debut->toDateString() === $item['date_debut'] && $m->date_fin->toDateString() === $item['date_fin']);
                }
                $module = $candidats->count() === 1 ? $candidats->first() : null;
                if ($module) {
                    $reserves[] = $module->id;
                }
            }
            $modules[$i] = $module;
        }
        $calendrier->update(['updated_by' => $userId]);
        $calendrier->evenements()->delete();
        foreach ($existants->except(collect($modules)->filter()->pluck('id')->all()) as $module) {
            $this->supprimerModule($module, $userId);
        }
        // Libérer les ordres avant un réordonnancement (contrainte unique).
        $ordres = $existants->pluck('ordre')->all();
        $temporaire = 101;
        foreach (array_filter($modules) as $module) {
            while (in_array($temporaire, $ordres, true)) {
                $temporaire++;
            }
            $module->update(['ordre' => $temporaire++]);
        }
        foreach ($data['modules'] as $i => $item) {
            $module = $modules[$i] ?? $calendrier->modules()->make();
            $module->fill([
                'libelle' => $item['libelle'], 'ordre' => $i + 1,
                'date_debut' => $item['date_debut'], 'date_fin' => $item['date_fin'],
            ]);
            if ($module->exists && $module->isDirty(['libelle', 'date_debut', 'date_fin'])) {
                PublicationProgramme::where('id_module_calendrier', $module->id)->where('statut', 'publie')
                    ->update(['statut' => 'non_publie', 'date_retrait' => now(), 'retire_par' => $userId]);
            }
            $module->save();
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

    /** À appeler dans la transaction de modification du calendrier. */
    public function supprimerModule(ModuleCalendrier $module, int $userId): void
    {
        Creneau::where('id_module_calendrier', $module->id)->update(['deleted_by' => $userId]);
        Creneau::where('id_module_calendrier', $module->id)->delete();
        SeanceCahierTexte::where('id_module_calendrier', $module->id)->where('statut', 'prevue')
            ->where('date_prevue', '>=', now()->toDateString())->delete();
        $module->delete();
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
                'id' => $module->id, 'libelle' => $module->libelle, ...$periode($module),
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
