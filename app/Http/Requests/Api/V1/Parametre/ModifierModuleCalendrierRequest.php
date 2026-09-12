<?php

namespace App\Http\Requests\Api\V1\Parametre;

use App\Models\CalendrierAcademique;
use App\Models\ModuleCalendrier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ModifierModuleCalendrierRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $module = ModuleCalendrier::find($this->route('id'));
        $calendrierId = $this->integer('id_calendrier') ?: $module?->id_calendrier;
        $presence = $this->isMethod('PATCH') ? 'sometimes' : 'required';
        return [
            'id_calendrier' => [$presence, 'integer', 'exists:calendriers_academiques,id'],
            'libelle' => [$presence, 'string', 'max:180'],
            'ordre' => [$presence, 'integer', 'min:1', 'max:65535', Rule::unique('modules_calendrier')->where('id_calendrier', $calendrierId)->ignore($this->route('id'))],
            'date_debut' => [$presence, 'date_format:Y-m-d'],
            'date_fin' => [$presence, 'date_format:Y-m-d'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) return;
            $module = ModuleCalendrier::with('evenements')->find($this->route('id'));
            if (! $module) return;
            $calendrierId = $this->integer('id_calendrier') ?: $module->id_calendrier;
            $calendrier = CalendrierAcademique::with('anneeAcademique')->find($calendrierId);
            $debut = $this->input('date_debut', $module->date_debut->toDateString());
            $fin = $this->input('date_fin', $module->date_fin->toDateString());
            if ($fin < $debut) {
                $validator->errors()->add('date_fin', 'La date de fin doit être postérieure ou égale à la date de début.');
                return;
            }
            if ($debut < $calendrier->anneeAcademique->date_debut->toDateString() || $fin > $calendrier->anneeAcademique->date_fin->toDateString()) {
                $validator->errors()->add('date_debut', 'La période doit être comprise dans l’année académique.');
            }
            if ($calendrier->modules()->whereKeyNot($module->id)->where('date_debut', '<=', $fin)->where('date_fin', '>=', $debut)->exists()) {
                $validator->errors()->add('date_debut', 'Deux modules du calendrier ne peuvent pas se chevaucher.');
            }
            if ($module->evenements->contains(fn ($e) => $e->type === 'examen' && ($e->date_debut->toDateString() < $debut || $e->date_fin->toDateString() > $fin))) {
                $validator->errors()->add('date_debut', 'Les examens existants doivent rester à l’intérieur du module.');
            }
        }];
    }
}
