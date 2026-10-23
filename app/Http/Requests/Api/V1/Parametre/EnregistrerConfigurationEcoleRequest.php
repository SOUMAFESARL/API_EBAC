<?php

namespace App\Http\Requests\Api\V1\Parametre;

use App\Models\ConfigurationEcole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EnregistrerConfigurationEcoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $existe = ConfigurationEcole::whereKey(1)->exists();
        $regles = [
            'nom_ecole' => [$existe ? 'sometimes' : 'required', 'required', 'string', 'max:180'],
            'code_ecole' => [$existe ? 'sometimes' : 'required', 'required', 'string', 'max:50'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'telephone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'cellulaire' => ['sometimes', 'nullable', 'string', 'max:30'],
            'biographie' => ['sometimes', 'nullable', 'string', 'max:50000'],
            'sigle' => ['sometimes', 'nullable', 'string', 'max:50'],
            'site_web' => ['sometimes', 'nullable', 'url:http,https', 'max:500'],
            'directeur_civilite_id' => ['sometimes', 'nullable', 'integer', Rule::exists('civilite', 'id')->whereNull('deleted_at')],
            'directeur_academique_civilite_id' => ['sometimes', 'nullable', 'integer', Rule::exists('civilite', 'id')->whereNull('deleted_at')],
        ];
        foreach (['slogan', 'adresse', 'fondateur', 'directeur_actuel', 'situation_geographique', 'ville', 'region',
            'nom_plateforme', 'arrete', 'boite_postale', 'directeur_nom', 'directeur_prenoms',
            'directeur_academique_nom', 'directeur_academique_prenoms'] as $champ) {
            $regles[$champ] = ['sometimes', 'nullable', 'string', 'max:255'];
        }
        foreach (['logo', 'favicon', 'signature', 'signature_directeur', 'signature_directeur_academique'] as $type) {
            $regles[$type] = $type === 'favicon'
                ? ['sometimes', 'nullable', 'file', 'mimes:ico,png,jpg,jpeg,webp', 'extensions:ico,png,jpg,jpeg,webp', 'max:1024']
                : ['sometimes', 'nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'extensions:png,jpg,jpeg,webp', 'max:2048'];
        }

        return $regles;
    }
}
