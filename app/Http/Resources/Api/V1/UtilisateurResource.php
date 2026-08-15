<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UtilisateurResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'civilite_id' => $this->civilite_id,
            'matricule' => $this->matricule,
            'code' => $this->code,
            'user_code' => $this->user_code,
            'user_id' => $this->user_id,
            'nom' => $this->nom,
            'prenoms' => $this->prenoms,
            'photo' => $this->photo,
            'photo_url' => $this->photo
                ? route('api.v1.utilisateurs.photo', ['compte' => $this->id])
                : null,
            'email' => $this->email,
            'id_role' => $this->id_role,
            'is_active' => $this->is_active,
            'statut' => $this->statut,
            'tentatives_echouees' => $this->tentatives_echouees,
            'deux_fa_active' => $this->deux_fa_active,
            'cree_le' => $this->cree_le?->toISOString(),
            'derniere_connexion' => $this->derniere_connexion?->toISOString(),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'deleted_by' => $this->deleted_by,
            'role' => $this->whenLoaded('role', fn () => [
                'id' => $this->role->id,
                'code' => $this->role->code,
                'libelle' => $this->role->libelle,
            ]),
            'civilite' => $this->whenLoaded('civilite', fn () => $this->civilite ? [
                'id' => $this->civilite->id,
                'code' => $this->civilite->code,
                'name' => $this->civilite->name,
                'abreviation' => $this->civilite->abreviation,
            ] : null),
        ];
    }
}
