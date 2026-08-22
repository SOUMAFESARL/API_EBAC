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
            'civilite_id' => ['required', 'integer', 'exists:civilite,id'],
            'date_naissance' => ['nullable', 'date', 'before:today'],
            'lieu_naissance' => ['nullable', 'string', 'max:150'],
            'nationalite' => ['nullable', 'string', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:150', 'unique:etudiants,email'],
            'telephone' => ['required', 'string', 'max:30'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'eglise_id' => ['required', 'integer', Rule::exists('eglises', 'id')->whereNull('deleted_at')],
            'statut_professionnel' => ['nullable', 'string', 'max:100'],
            'photo_identite' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'documents' => ['sometimes', 'array', 'max:10'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
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
            'eglise_id.required' => 'L’église est obligatoire.',
            'eglise_id.exists' => 'L’église sélectionnée est invalide.',
            'civilite_id.required' => 'La civilité est obligatoire.',
            'civilite_id.exists' => 'La civilité sélectionnée est invalide.',
            'photo_identite.required' => 'La photo d’identité est obligatoire.',
            'photo_identite.image' => 'La photo d’identité doit être une image valide.',
        ];
    }
}
