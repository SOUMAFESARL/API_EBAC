<?php

namespace App\DTOs\Api\V1\Parametre;

final readonly class UniteEnseignementDTO
{
    /** @param array<string, mixed> $donnees */
    private function __construct(private array $donnees) {}

    /** @param array<string, mixed> $donnees */
    public static function fromArray(array $donnees): self
    {
        return new self(array_intersect_key($donnees, array_flip(['libelle', 'code', 'description', 'statut'])));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->donnees;
    }
}
