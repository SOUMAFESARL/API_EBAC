<?php

namespace App\Http\Requests\Api\V1\Administration;

use App\Rules\UtilisateurEstEnseignant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreerAffectationEnseignantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enseignant_id' => ['required', 'integer', new UtilisateurEstEnseignant],
            'portee' => ['required', Rule::in(['matiere', 'cours'])],
            'id_matiere' => ['required', 'integer', \Illuminate\Validation\Rule::exists('matieres', 'id')->whereNull('deleted_at')],
            'id_cours' => [Rule::requiredIf($this->input('portee') === 'cours'), Rule::prohibitedIf($this->input('portee') === 'matiere'), 'nullable', 'integer', \Illuminate\Validation\Rule::exists('cours', 'id')->whereNull('deleted_at')],
            'date_debut' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
