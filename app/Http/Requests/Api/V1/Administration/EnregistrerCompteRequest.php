<?php

namespace App\Http\Requests\Api\V1\Administration;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class EnregistrerCompteRequest extends FormRequest
{
    /**
     * Ouvrir l'accès d'un candidat (`gestion-academique.preinscriptions.ouvrir-acces`)
     * ne crée qu'un compte Étudiant ; tout autre compte exige
     * `securite-comptes.utilisateurs.creer`. ADMIN détient tout.
     */
    public function authorize(): bool
    {
        $utilisateur = $this->user();
        if ($utilisateur?->role?->code === 'ADMIN'
            || in_array('securite-comptes.utilisateurs.creer', $utilisateur?->codesPermissions() ?? [], true)) {
            return true;
        }

        $roleEtudiant = Role::query()->where('code', 'ETUDIANT')->value('id');

        return $roleEtudiant !== null && (int) $this->input('id_role') === (int) $roleEtudiant;
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json([
            'message' => "Ce droit n'ouvre que des comptes Étudiant.",
            'permissions_requises' => ['securite-comptes.utilisateurs.creer'],
        ], 403));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'civilite_id' => ['required', 'integer', 'exists:civilite,id'],
            'code' => ['prohibited'],
            'user_code' => ['prohibited'],
            'user_id' => ['prohibited'],
            'nom' => ['required', 'string', 'max:150'],
            'prenoms' => ['required', 'string', 'max:150'],
            'photo' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->withoutTrashed()],
            'id_role' => ['required', 'integer', 'exists:roles,id'],
            'is_active' => ['sometimes', 'boolean'],
            'statut' => ['sometimes', Rule::in(['Actif', 'Suspendu', 'Bloqué', 'Désactivé'])],
            'deux_fa_active' => ['sometimes', 'boolean'],
        ];
    }
}
