<?php

namespace App\Http\Requests\Api\V1\Parametre;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModifierNiveauRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $niveau = $this->route('id');

        return [
            'libelle' => ['sometimes', 'required', 'string', 'max:100'],
            'code' => ['sometimes', 'required', 'string', 'max:20', Rule::unique('niveaux', 'code')->ignore($niveau)],
            'rang' => ['sometimes', 'required', 'integer', 'min:1', 'max:65535', Rule::unique('niveaux', 'rang')->ignore($niveau)],
            'statut' => ['sometimes', Rule::in(['Actif', 'Archive'])],
        ];
    }
}
