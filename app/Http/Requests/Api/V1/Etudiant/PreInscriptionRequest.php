<?php

namespace App\Http\Requests\Api\V1\Etudiant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreInscriptionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:150'],
            'prenoms' => ['required', 'string', 'max:150'],
            'sexe' => ['nullable', 'string', 'max:20'],
            'civilite_id' => ['nullable', 'integer', 'exists:civilite,id'],
            'date_naissance' => ['nullable', 'date', 'before:today'],
            'lieu_naissance' => ['nullable', 'string', 'max:150'],
            'nationalite' => ['nullable', 'string', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:150'],
            'telephone' => ['required', 'string', 'max:30'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'eglise_id' => ['nullable', 'integer', Rule::exists('eglises', 'id')->whereNull('deleted_at')],
            'statut_professionnel' => ['nullable', 'string', 'max:100'],
            'pieces_requises' => ['nullable', 'array'],
            'pieces_requises.*' => ['string', 'max:100'],
            'observations' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required' => 'Le nom est obligatoire.',
            'prenoms.required' => 'Les prénoms sont obligatoires.',
            'telephone.required' => 'Le numéro de téléphone est obligatoire.',
            'email.required' => 'L’adresse e-mail est obligatoire.',
        ];
    }
}
