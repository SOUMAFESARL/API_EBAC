<?php

namespace App\Http\Requests\Api\V1\Etudiant;

use App\Models\Etudiant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModifierEmailPreInscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Etudiant $preinscription */
        $preinscription = $this->route('preinscription');

        return [
            'email' => [
                'required',
                'email:rfc',
                'max:150',
                Rule::unique('etudiants', 'email')->ignore($preinscription->id),
                Rule::unique('users', 'email'),
            ],
        ];
    }
}
