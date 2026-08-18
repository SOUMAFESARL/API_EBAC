<?php

namespace App\Http\Requests\Api\V1\Eglise;

use Illuminate\Validation\Rule;

class ModifierEgliseRequest extends CreerEgliseRequest
{
    public function rules(): array
    {
        $regles = $this->regles(true);
        $regles['sigle'] = [
            'sometimes', 'nullable', 'string', 'max:30',
            Rule::unique('eglises', 'sigle')->ignore($this->route('eglise')),
        ];

        return $regles;
    }
}
