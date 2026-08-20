<?php

namespace App\Http\Requests\Api\V1\Parametre;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreerCoursRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'id_module' => ['required', 'integer', 'exists:modules,id'],
            'code' => ['nullable', 'string', 'max:50'],
            'libelle' => ['required', 'string', 'max:180', Rule::unique('cours')->where('id_module', $this->integer('id_module'))],
            'volume_horaire' => ['sometimes', 'numeric', 'min:0', 'max:9999.99'],
            'coefficient' => ['sometimes', 'numeric', 'gt:0', 'max:999.99'],
            'ordre' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'actif' => ['sometimes', 'boolean'],
        ];
    }
}
