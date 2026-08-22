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
            'num_promotion' => [$this->isMethod('put') ? 'required' : 'sometimes', 'integer', 'min:1', 'max:65535'],
            'annee_entree' => [$this->isMethod('put') ? 'required' : 'sometimes', 'integer', 'digits:4', 'min:1900', 'max:9999'],
            'id_niveau' => ['sometimes', 'required', 'integer', 'exists:niveaux,id'],
            'statut' => ['sometimes', 'required', 'string', 'max:30'],
            'date_ouverture' => ['nullable', 'date'],
            'date_cloture' => ['nullable', 'date'],
        ];
    }
}
