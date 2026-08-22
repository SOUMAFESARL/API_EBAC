<?php

namespace App\Http\Requests\Api\V1\Parametre;

use App\Rules\UtilisateurEstEnseignant;
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
            'enseignant_id' => ['nullable', 'integer', new UtilisateurEstEnseignant],
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
            'modules' => ['sometimes', 'array', 'min:1'],
            'modules.*.code' => ['nullable', 'string', 'max:50'],
            'modules.*.libelle' => ['required', 'string', 'max:180', 'distinct:strict'],
            'modules.*.ordre' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'modules.*.description' => ['nullable', 'string'],
            'modules.*.cours' => ['required', 'array', 'min:1'],
            'modules.*.cours.*.code' => ['nullable', 'string', 'max:50'],
            'modules.*.cours.*.libelle' => ['required', 'string', 'max:180'],
            'modules.*.cours.*.volume_horaire' => ['sometimes', 'numeric', 'min:0', 'max:9999.99'],
            'modules.*.cours.*.coefficient' => ['sometimes', 'numeric', 'gt:0', 'max:999.99'],
            'modules.*.cours.*.ordre' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'modules.*.cours.*.actif' => ['sometimes', 'boolean'],
        ];
    }
}
