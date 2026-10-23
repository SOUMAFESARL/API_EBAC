<?php

namespace App\DTOs\Api\V1\Parametre;

final readonly class ConfigurationEcoleDTO
{
    /** @param array<string, mixed> $donnees Données validées, fichiers remplacés par leurs chemins de stockage. */
    private function __construct(private array $donnees) {}

    /**
     * Les champs complémentaires (sigle, plateforme, références et identités des
     * directeurs) transitent sans renommer ni remplacer les champs historiques.
     * Les champs omis restent absents ; null permet un effacement explicite.
     *
     * @param array<string, mixed> $donnees
     */
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
