<?php

namespace App\Http\Requests\Api\V1\Eglise;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreerEgliseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->regles(false);
    }

    /** @return array<string, mixed> */
    protected function regles(bool $modification): array
    {
        $requis = $modification ? 'sometimes' : 'required';

        return [
            'code' => ['exclude'],
            'user_id' => ['prohibited'],
            'user_code' => ['prohibited'],
            'created_by' => ['prohibited'],
            'updated_by' => ['prohibited'],
            'deleted_by' => ['prohibited'],
            'nom' => [$requis, 'string', 'max:180'],
            'sigle' => ['sometimes', 'nullable', 'string', 'max:30', 'unique:eglises,sigle'],
            'pasteur_principal' => ['sometimes', 'nullable', 'string', 'max:180'],
            'denomination' => ['sometimes', 'nullable', 'string', 'max:180'],
            'adresse' => ['sometimes', 'nullable', 'string', 'max:255'],
            'region' => ['sometimes', 'nullable', 'string', 'max:120'],
            'district' => ['sometimes', 'nullable', 'string', 'max:120'],
            'ville_commune' => [$requis, 'string', 'max:120'],
            'telephone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'statut' => ['sometimes', Rule::in(['Active', 'Suspendue', 'Archivée'])],
            'capacite_max_stagiaires' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'representants' => ['sometimes', 'nullable', 'array'],
            'representants.*' => ['array'],
            'representants.*.nom' => ['required', 'string', 'max:150'],
            'representants.*.prenoms' => ['sometimes', 'nullable', 'string', 'max:150'],
            'representants.*.fonction' => ['sometimes', 'nullable', 'string', 'max:100'],
            'representants.*.telephone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'representants.*.email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'observations' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
