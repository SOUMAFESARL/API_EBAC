<?php

namespace App\Http\Requests\Api\V1\Parametre;

use Illuminate\Foundation\Http\FormRequest;

class EnregistrerCreneauRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $presence = $this->isMethod('PATCH') ? 'sometimes' : 'required';
        $rules = [
            'jour' => [$presence, 'required', 'integer', 'between:1,7'],
            'heure_debut' => [$presence, 'required', 'date_format:H:i'],
            'heure_fin' => [$presence, 'required', 'date_format:H:i'],
            'id_cours' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'id_promotion' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
        foreach (['id_module_calendrier', 'id_niveau', 'id_matiere', 'enseignant_id', 'id_salle'] as $key) {
            $rules[$key] = [$presence, 'required', 'integer', 'min:1'];
        }

        return $rules;
    }
}
