<?php

namespace App\Http\Requests\Api\V1\Parametre;

use App\Models\CalendrierAcademique;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CreerModuleCalendrierRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'id_calendrier' => ['required', 'integer', 'exists:calendriers_academiques,id'],
            'libelle' => ['required', 'string', 'max:180'],
            'ordre' => ['required', 'integer', 'min:1', 'max:65535', Rule::unique('modules_calendrier')->where('id_calendrier', $this->integer('id_calendrier'))],
            'date_debut' => ['required', 'date_format:Y-m-d'],
            'date_fin' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_debut'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) return;
            $calendrier = CalendrierAcademique::with('anneeAcademique')->find($this->integer('id_calendrier'));
            if (! $calendrier?->anneeAcademique) return;
            $debut = $this->string('date_debut')->toString();
            $fin = $this->string('date_fin')->toString();
            $annee = $calendrier->anneeAcademique;
            if ($debut < $annee->date_debut->toDateString() || $fin > $annee->date_fin->toDateString()) {
                $validator->errors()->add('date_debut', 'La période doit être comprise dans l’année académique.');
            }
            if ($calendrier->modules()->where('date_debut', '<=', $fin)->where('date_fin', '>=', $debut)->exists()) {
                $validator->errors()->add('date_debut', 'Deux modules du calendrier ne peuvent pas se chevaucher.');
            }
        }];
    }
}
