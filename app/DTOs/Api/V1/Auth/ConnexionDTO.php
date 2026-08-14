<?php

namespace App\DTOs\Api\V1\Auth;

final readonly class ConnexionDTO
{
    public function __construct(
        public string $email,
        public string $password,
        public string $nomAppareil = 'api',
    ) {}

    /**
     * @param  array{email: string, password: string, nom_appareil?: string}  $donnees
     */
    public static function fromArray(array $donnees): self
    {
        return new self(
            email: $donnees['email'],
            password: $donnees['password'],
            nomAppareil: $donnees['nom_appareil'] ?? 'api',
        );
    }
}
