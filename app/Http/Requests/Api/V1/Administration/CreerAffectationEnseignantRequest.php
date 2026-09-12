<?php

namespace App\Http\Requests\Api\V1\Administration;

use App\Rules\UtilisateurEstEnseignant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreerAffectationEnseignantRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'id_annee_academique' => ['required', 'integer', 'exists:annees_academiques,id'],
            'enseignant_id' => ['required', 'integer', new UtilisateurEstEnseignant],
            'portee' => ['required', Rule::in(['matiere', 'cours'])],
            'id_matiere' => ['required', 'integer', 'exists:matieres,id'],
            'id_cours' => [Rule::requiredIf($this->input('portee') === 'cours'), Rule::prohibitedIf($this->input('portee') === 'matiere'), 'nullable', 'integer', 'exists:cours,id'],
            'date_debut' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
