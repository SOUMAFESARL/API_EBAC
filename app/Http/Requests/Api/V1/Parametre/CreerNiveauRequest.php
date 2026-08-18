<?php

namespace App\Http\Requests\Api\V1\Parametre;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreerNiveauRequest extends FormRequest
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
        return [
            'libelle' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', 'unique:niveaux,code'],
            'rang' => ['required', 'integer', 'min:1', 'max:65535', 'unique:niveaux,rang'],
            'statut' => ['sometimes', Rule::in(['Actif', 'Archive'])],
        ];
    }
}
