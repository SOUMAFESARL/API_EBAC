<?php

namespace App\DTOs\Api\V1\Administration;

use App\Models\User;

final readonly class CreerCompteDTO
{
    public function __construct(
        public string $matricule,
        public string $code,
        public string $userCode,
        public string $userId,
        public string $nom,
        public string $prenoms,
        public string $email,
        public string $password,
        public int $idRole,
        public bool $isActive,
        public string $statut,
        public bool $deuxFaActive,
        public int $createdBy,
        public string $createdByUserId,
        public string $createdByUserCode,
        public ?string $photo = null,
    ) {}

    /**
     * @param  array<string, mixed>  $donnees
     */
    public static function fromArray(
        array $donnees,
        string $motDePasseTemporaire,
        string $matricule,
        User $administrateur,
    ): self {
        return new self(
            matricule: $matricule,
            code: $donnees['code'],
            userCode: $donnees['user_code'],
            userId: $donnees['user_id'],
            nom: $donnees['nom'],
            prenoms: $donnees['prenoms'],
            email: $donnees['email'],
            password: $motDePasseTemporaire,
            idRole: (int) $donnees['id_role'],
            isActive: (bool) ($donnees['is_active'] ?? true),
            statut: $donnees['statut'] ?? 'Actif',
            deuxFaActive: (bool) ($donnees['deux_fa_active'] ?? false),
            createdBy: $administrateur->id,
            createdByUserId: $administrateur->user_id,
            createdByUserCode: $administrateur->user_code,
            photo: $donnees['photo'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'matricule' => $this->matricule,
            'code' => $this->code,
            'user_code' => $this->userCode,
            'user_id' => $this->userId,
            'nom' => $this->nom,
            'prenoms' => $this->prenoms,
            'photo' => $this->photo,
            'email' => $this->email,
            'password' => $this->password,
            'id_role' => $this->idRole,
            'is_active' => $this->isActive,
            'statut' => $this->statut,
            'deux_fa_active' => $this->deuxFaActive,
            'created_by' => $this->createdBy,
            'created_by_user_id' => $this->createdByUserId,
            'created_by_user_code' => $this->createdByUserCode,
        ];
    }
}
