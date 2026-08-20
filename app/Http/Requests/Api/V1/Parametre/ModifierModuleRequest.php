<?php

namespace App\Http\Requests\Api\V1\Parametre;

use App\Models\Module;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModifierModuleRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $module = Module::query()->find($this->route('id'));
        $matiere = $this->integer('id_matiere') ?: $module?->id_matiere;

        return [
            'id_matiere' => ['sometimes', 'required', 'integer', 'exists:matieres,id'],
            'code' => ['nullable', 'string', 'max:50'],
            'libelle' => ['sometimes', 'required', 'string', 'max:180', Rule::unique('modules')->where('id_matiere', $matiere)->ignore($this->route('id'))],
            'ordre' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'description' => ['nullable', 'string'],
        ];
    }
}
