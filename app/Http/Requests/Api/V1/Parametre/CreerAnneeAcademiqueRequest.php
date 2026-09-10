<?php

namespace App\Http\Requests\Api\V1\Parametre;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreerAnneeAcademiqueRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'libelle' => ['required', 'string', 'max:20', Rule::unique('annees_academiques', 'libelle')->withoutTrashed()],
            'date_debut' => ['required', 'date_format:Y-m-d'],
            'date_fin' => ['required', 'date_format:Y-m-d', 'after:date_debut'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
