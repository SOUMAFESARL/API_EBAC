<?php

namespace App\Http\Requests\Api\V1\Parametre;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModifierUniteEnseignementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'libelle' => ['sometimes', 'required', 'string', 'max:180'],
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('unites_enseignement', 'code')->ignore($this->route('id'))],
            'description' => ['sometimes', 'nullable', 'string'],
            'statut' => ['sometimes', 'required', Rule::in(['Actif', 'Archive'])],
        ];
    }
}
