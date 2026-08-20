<?php

namespace App\Http\Requests\Api\V1\Parametre;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreerModuleRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'id_matiere' => ['required', 'integer', 'exists:matieres,id'],
            'code' => ['nullable', 'string', 'max:50'],
            'libelle' => ['required', 'string', 'max:180', Rule::unique('modules')->where('id_matiere', $this->integer('id_matiere'))],
            'ordre' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'description' => ['nullable', 'string'],
        ];
    }
}
