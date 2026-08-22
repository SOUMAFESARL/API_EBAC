<?php

namespace App\Http\Requests\Api\V1\Parametre;

use App\Rules\UtilisateurEstEnseignant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModifierMatiereRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('matieres', 'code')->ignore($this->route('id'))],
            'libelle' => ['sometimes', 'required', 'string', 'max:180'],
            'id_niveau' => ['sometimes', 'required', 'integer', 'exists:niveaux,id'],
            'enseignant_id' => ['sometimes', 'nullable', 'integer', new UtilisateurEstEnseignant],
            'coefficient' => ['sometimes', 'numeric', 'gt:0', 'max:999.99'],
            'volume_horaire' => ['sometimes', 'numeric', 'min:0', 'max:9999.99'],
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
