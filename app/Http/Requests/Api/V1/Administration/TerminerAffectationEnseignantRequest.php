<?php

namespace App\Http\Requests\Api\V1\Administration;

use Illuminate\Foundation\Http\FormRequest;

class TerminerAffectationEnseignantRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return ['date_fin' => ['required', 'date_format:Y-m-d'], 'motif' => ['nullable', 'string', 'max:2000']];
    }
}
