<?php

namespace App\Http\Requests\Api\V1\Parametre;

use Illuminate\Foundation\Http\FormRequest;

class CreerCiviliteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:10', 'unique:civilite,code'],
            'name' => ['required', 'string', 'max:50'],
            'abreviation' => ['sometimes', 'nullable', 'string', 'max:10'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'actif' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => mb_strtoupper(trim((string) $this->input('code')))]);
        }
    }
}
