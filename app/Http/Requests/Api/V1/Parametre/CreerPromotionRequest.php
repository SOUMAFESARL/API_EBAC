<?php

namespace App\Http\Requests\Api\V1\Parametre;

use Illuminate\Foundation\Http\FormRequest;

class CreerPromotionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', 'unique:promotions,code'],
            'num_promotion' => ['required', 'integer', 'min:1', 'max:65535'],
            'annee_entree' => ['required', 'integer', 'digits:4', 'min:1900', 'max:9999'],
            'id_niveau' => ['required', 'integer', 'exists:niveaux,id'],
            'statut' => ['sometimes', 'string', 'max:30'],
            'date_ouverture' => ['nullable', 'date'],
            'date_cloture' => ['nullable', 'date', 'after_or_equal:date_ouverture'],
        ];
    }
}
