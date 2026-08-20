<?php

namespace App\DTOs\Api\V1\Parametre;

final readonly class ModuleDTO
{
    /** @param array<string, mixed> $donnees */
    private function __construct(private array $donnees) {}

    /** @param array<string, mixed> $donnees */
    public static function fromArray(array $donnees): self
    {
        foreach (['id_matiere', 'ordre'] as $champ) {
            if (array_key_exists($champ, $donnees)) {
                $donnees[$champ] = (int) $donnees[$champ];
            }
        }

        return new self($donnees);
    }

    /** @return array<string, mixed> */
    public function toArray(): array { return $this->donnees; }
}
