<?php

namespace App\Http\Requests\Api\V1\Parametre;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModifierPromotionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'code' => ['sometimes', 'required', 'string', 'max:30', Rule::unique('promotions', 'code')->ignore($this->route('id'))],
            'rang' => [$this->isMethod('put') ? 'required' : 'sometimes', 'integer', 'min:1', 'max:65535'],
            'id_annee_academique' => [$this->isMethod('put') ? 'required' : 'sometimes', 'integer', 'exists:annees_academiques,id'],
            'id_niveau' => ['sometimes', 'required', 'integer', 'exists:niveaux,id'],
            'capacite' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'statut' => ['sometimes', 'required', 'string', 'max:30'],
            'date_ouverture' => ['nullable', 'date'],
            'date_cloture' => ['nullable', 'date'],
        ];
    }
}
