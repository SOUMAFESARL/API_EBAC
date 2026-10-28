<?php

namespace App\Http\Requests\Api\V1\Parametre;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreerUniteEnseignementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'libelle' => ['required', 'string', 'max:180'],
            'code' => ['required', 'string', 'max:50', Rule::unique('unites_enseignement', 'code')],
            'description' => ['nullable', 'string'],
            'statut' => ['required', Rule::in(['Actif', 'Archive'])],
        ];
    }
}
