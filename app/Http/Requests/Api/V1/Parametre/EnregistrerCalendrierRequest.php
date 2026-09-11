<?php

namespace App\Http\Requests\Api\V1\Parametre;

use Illuminate\Foundation\Http\FormRequest;

class EnregistrerCalendrierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'modules' => ['required', 'array', 'list', 'min:1', 'max:100'],
            'modules.*' => ['required', 'array:libelle,date_debut,date_fin,examens,rattrapages'],
            'modules.*.libelle' => ['required', 'string', 'max:180'],
            'modules.*.date_debut' => ['required', 'date_format:Y-m-d'],
            'modules.*.date_fin' => ['required', 'date_format:Y-m-d', 'after_or_equal:modules.*.date_debut'],
            'jours_feries' => ['present', 'array', 'list', 'max:366'],
            'jours_feries.*' => ['required', 'array:libelle,date'],
            'jours_feries.*.libelle' => ['required', 'string', 'max:180'],
            'jours_feries.*.date' => ['required', 'date_format:Y-m-d', 'distinct'],
            'conges' => ['present', 'array', 'list', 'max:100'],
            'conges.*' => ['required', 'array:libelle,date_debut,date_fin'],
            'conges.*.libelle' => ['required', 'string', 'max:180'],
            'grandes_vacances' => ['present', 'nullable', 'array:libelle,date_debut,date_fin', 'required_array_keys:date_debut,date_fin'],
            'grandes_vacances.libelle' => ['nullable', 'string', 'max:180'],
        ];
        foreach (['modules.*.examens', 'modules.*.rattrapages'] as $key) {
            $rules[$key] = ['present', 'array', 'list', 'max:100'];
            $rules[$key.'.*'] = ['required', 'array:libelle,date_debut,date_fin'];
            $rules[$key.'.*.libelle'] = ['sometimes', 'nullable', 'string', 'max:180'];
        }
        foreach (['modules.*.examens.*', 'modules.*.rattrapages.*', 'conges.*', 'grandes_vacances'] as $key) {
            $required = $key === 'grandes_vacances' ? 'required_with:grandes_vacances' : 'required';
            $rules[$key.'.date_debut'] = [$required, 'date_format:Y-m-d'];
            $rules[$key.'.date_fin'] = [$required, 'date_format:Y-m-d', 'after_or_equal:'.$key.'.date_debut'];
        }

        return $rules;
    }
}
