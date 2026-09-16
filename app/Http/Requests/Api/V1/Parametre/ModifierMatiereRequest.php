<?php

namespace App\Http\Requests\Api\V1\Parametre;

use App\Rules\UtilisateurEstEnseignant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModifierMatiereRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

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
            'module_calendrier_id' => ['sometimes', 'array'],
            'module_calendrier_id.*' => ['integer', 'distinct', 'exists:modules_calendrier,id'],
            // Modules imbriqués : la présence du tableau déclenche la synchronisation complète
            'modules' => ['sometimes', 'array', 'min:1'],
            'modules.*.id' => ['sometimes', 'nullable', 'integer', 'exists:modules,id'],
            'modules.*.code' => ['nullable', 'string', 'max:50'],
            'modules.*.libelle' => ['required_with:modules', 'string', 'max:180'],
            'modules.*.ordre' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'modules.*.description' => ['nullable', 'string'],
            // Cours imbriqués dans chaque module
            'modules.*.cours' => ['sometimes', 'array', 'min:1'],
            'modules.*.cours.*.id' => ['sometimes', 'nullable', 'integer', 'exists:cours,id'],
            'modules.*.cours.*.code' => ['nullable', 'string', 'max:50'],
            'modules.*.cours.*.libelle' => ['required_with:modules.*.cours', 'string', 'max:180'],
            'modules.*.cours.*.volume_horaire' => ['sometimes', 'numeric', 'min:0', 'max:9999.99'],
            'modules.*.cours.*.coefficient' => ['sometimes', 'numeric', 'gt:0', 'max:999.99'],
            'modules.*.cours.*.ordre' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'modules.*.cours.*.actif' => ['sometimes', 'boolean'],
        ];
    }
}
