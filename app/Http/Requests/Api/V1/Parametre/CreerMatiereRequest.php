<?php

namespace App\Http\Requests\Api\V1\Parametre;

use Illuminate\Foundation\Http\FormRequest;

class CreerMatiereRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:matieres,code'],
            'libelle' => ['required', 'string', 'max:180'],
            'id_niveau' => ['required', 'integer', 'exists:niveaux,id'],
            'coefficient' => ['sometimes', 'numeric', 'gt:0', 'max:999.99'],
            'type' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'objectifs' => ['nullable', 'string'],
            'prerequis' => ['nullable', 'string'],
            'note_validation' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'obligatoire' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
            'version' => ['sometimes', 'integer', 'min:1', 'max:65535'],
        ];
    }
}
