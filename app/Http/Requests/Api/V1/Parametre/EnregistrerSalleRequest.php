<?php

namespace App\Http\Requests\Api\V1\Parametre;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EnregistrerSalleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => mb_strtoupper(trim($this->input('code')))]);
        }
    }

    public function rules(): array
    {
        $presence = $this->isMethod('PATCH') ? 'sometimes' : 'required';

        return [
            'nom' => [$presence, 'required', 'string', 'max:180'],
            'code' => [$presence, 'required', 'string', 'max:30', Rule::unique('salles', 'code')->withoutTrashed()->ignore($this->route('id'))],
            'statut' => ['sometimes', 'required', Rule::in(['Actif', 'Inactif'])],
        ];
    }
}
