<?php

namespace App\Http\Requests\Api\V1\Parametre;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModifierAnneeAcademiqueRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'libelle' => ['sometimes', 'required', 'string', 'max:20', Rule::unique('annees_academiques', 'libelle')->ignore($this->route('id'))],
            'date_debut' => ['sometimes', 'required', 'date'],
            'date_fin' => ['sometimes', 'required', 'date'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
