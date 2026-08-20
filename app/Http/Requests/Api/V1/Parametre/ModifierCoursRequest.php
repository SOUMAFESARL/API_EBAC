<?php

namespace App\Http\Requests\Api\V1\Parametre;

use App\Models\Cours;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModifierCoursRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $cours = Cours::query()->find($this->route('id'));
        $module = $this->integer('id_module') ?: $cours?->id_module;

        return [
            'id_module' => ['sometimes', 'required', 'integer', 'exists:modules,id'],
            'code' => ['nullable', 'string', 'max:50'],
            'libelle' => ['sometimes', 'required', 'string', 'max:180', Rule::unique('cours')->where('id_module', $module)->ignore($this->route('id'))],
            'volume_horaire' => ['sometimes', 'numeric', 'min:0', 'max:9999.99'],
            'coefficient' => ['sometimes', 'numeric', 'gt:0', 'max:999.99'],
            'ordre' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'actif' => ['sometimes', 'boolean'],
        ];
    }
}
