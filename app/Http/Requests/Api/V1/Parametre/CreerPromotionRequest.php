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
            'rang' => ['required', 'integer', 'min:1', 'max:65535'],
            'id_annee_academique' => ['required', 'integer', 'exists:annees_academiques,id'],
            'id_niveau' => ['required', 'integer', 'exists:niveaux,id'],
            'capacite' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'statut' => ['sometimes', 'string', 'max:30'],
            'date_ouverture' => ['nullable', 'date'],
            'date_cloture' => ['nullable', 'date', 'after_or_equal:date_ouverture'],
        ];
    }
}
