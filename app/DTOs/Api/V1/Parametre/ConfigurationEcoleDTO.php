<?php

namespace App\DTOs\Api\V1\Parametre;

final readonly class ConfigurationEcoleDTO
{
    /** @param array<string, mixed> $donnees Données validées, fichiers remplacés par leurs chemins de stockage. */
    private function __construct(private array $donnees) {}

    /** @param array<string, mixed> $donnees */
    public static function fromArray(array $donnees): self
    {
        return new self($donnees);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->donnees;
    }
}
