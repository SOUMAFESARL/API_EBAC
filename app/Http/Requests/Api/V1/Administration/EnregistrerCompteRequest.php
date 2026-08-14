<?php

namespace App\Http\Requests\Api\V1\Administration;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EnregistrerCompteRequest extends FormRequest
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
        return [
            'civilite_id' => ['sometimes', 'nullable', 'integer', 'exists:civilite,id'],
            'code' => ['prohibited'],
            'user_code' => ['prohibited'],
            'user_id' => ['required', 'string', 'max:150'],
            'nom' => ['required', 'string', 'max:150'],
            'prenoms' => ['required', 'string', 'max:150'],
            'photo' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'id_role' => ['required', 'integer', 'exists:roles,id'],
            'is_active' => ['sometimes', 'boolean'],
            'statut' => ['sometimes', Rule::in(['Actif', 'Suspendu', 'Bloqué', 'Désactivé'])],
            'deux_fa_active' => ['sometimes', 'boolean'],
        ];
    }
}
