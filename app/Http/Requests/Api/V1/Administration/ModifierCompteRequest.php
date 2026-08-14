<?php

namespace App\Http\Requests\Api\V1\Administration;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModifierCompteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $compte = $this->route('compte');

        return [
            'code' => ['sometimes', 'required', 'string', 'max:150'],
            'user_code' => ['sometimes', 'required', 'string', 'max:150'],
            'user_id' => ['sometimes', 'required', 'string', 'max:150'],
            'nom' => ['sometimes', 'required', 'string', 'max:150'],
            'prenoms' => ['sometimes', 'required', 'string', 'max:150'],
            'photo' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($compte),
            ],
            'password' => ['sometimes', 'required', 'string', 'min:8', 'confirmed'],
            'id_role' => ['sometimes', 'required', 'integer', 'exists:roles,id'],
            'is_active' => ['sometimes', 'boolean'],
            'statut' => ['sometimes', Rule::in(['Actif', 'Suspendu', 'Bloqué', 'Désactivé'])],
            'deux_fa_active' => ['sometimes', 'boolean'],
        ];
    }
}
