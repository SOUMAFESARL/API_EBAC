<?php

namespace App\Http\Requests\Api\V1\Parametre;

use App\Models\CalendrierAcademique;
use App\Models\EvenementCalendrier;
use App\Models\ModuleCalendrier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EnregistrerEvenementCalendrierRequest extends FormRequest
{
    private const TYPES = ['examen', 'rattrapage', 'jour_ferie', 'conge', 'grandes_vacances'];

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $event = EvenementCalendrier::find($this->route('id'));
        $type = $this->input('type', $event?->type);
        $moduleId = $this->has('id_module_calendrier') ? $this->input('id_module_calendrier') : $event?->id_module_calendrier;
        $presence = $this->isMethod('PATCH') ? 'sometimes' : 'required';

        return [
            'id_calendrier' => [$presence, 'integer', 'exists:calendriers_academiques,id'],
            'id_module_calendrier' => [
                Rule::requiredIf(in_array($type, ['examen', 'rattrapage'], true) && ! $moduleId),
                'nullable', 'integer', 'exists:modules_calendrier,id',
                Rule::prohibitedIf(in_array($type, ['jour_ferie', 'conge', 'grandes_vacances'], true)),
            ],
            'type' => [$presence, Rule::in(self::TYPES)],
            'libelle' => [Rule::requiredIf(in_array($type, ['jour_ferie', 'conge'], true)), 'nullable', 'string', 'max:180'],
            'date_debut' => [$presence, 'date_format:Y-m-d'],
            'date_fin' => [$presence, 'date_format:Y-m-d'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) return;
            $event = EvenementCalendrier::find($this->route('id'));
            $calendrierId = $this->integer('id_calendrier') ?: $event?->id_calendrier;
            $moduleId = $this->has('id_module_calendrier') ? $this->input('id_module_calendrier') : $event?->id_module_calendrier;
            $type = $this->input('type', $event?->type);
            $debut = $this->input('date_debut', $event?->date_debut?->toDateString());
            $fin = $this->input('date_fin', $event?->date_fin?->toDateString());
            $calendrier = CalendrierAcademique::with('anneeAcademique')->find($calendrierId);
            $module = $moduleId ? ModuleCalendrier::find($moduleId) : null;
            if (! $calendrier?->anneeAcademique || ! $debut || ! $fin) return;

            if ($fin < $debut) {
                $validator->errors()->add('date_fin', 'La date de fin doit être postérieure ou égale à la date de début.');
                return;
            }
            if ($module && $module->id_calendrier !== $calendrier->id) {
                $validator->errors()->add('id_module_calendrier', 'Le module doit appartenir au calendrier sélectionné.');
            }

            $anneeDebut = $calendrier->anneeAcademique->date_debut->toDateString();
            $anneeFin = $calendrier->anneeAcademique->date_fin->toDateString();
            if ($type !== 'grandes_vacances' && ($debut < $anneeDebut || $fin > $anneeFin)) {
                $validator->errors()->add('date_debut', 'La période doit être comprise dans l’année académique.');
            }
            if ($type === 'examen' && $module && ($debut < $module->date_debut->toDateString() || $fin > $module->date_fin->toDateString())) {
                $validator->errors()->add('date_debut', 'L’examen doit se tenir à l’intérieur du module.');
            }
            if ($type === 'rattrapage' && $module && $debut <= $module->date_fin->toDateString()) {
                $validator->errors()->add('date_debut', 'Le rattrapage doit commencer après la fin du module.');
            }
            if ($type === 'grandes_vacances') {
                $query = $calendrier->evenements()->where('type', 'grandes_vacances');
                if ($event) $query->whereKeyNot($event->id);
                if ($query->exists()) {
                    $validator->errors()->add('type', 'Ce calendrier possède déjà une période de grandes vacances.');
                }
                $derniereActivite = max(
                    $calendrier->modules()->max('date_fin') ?? $anneeDebut,
                    $calendrier->evenements()->when($event, fn ($q) => $q->whereKeyNot($event->id))->where('type', '!=', 'grandes_vacances')->max('date_fin') ?? $anneeDebut
                );
                if ($debut <= $derniereActivite) {
                    $validator->errors()->add('date_debut', 'Les grandes vacances doivent commencer après les autres activités.');
                }
            }
        }];
    }
}
