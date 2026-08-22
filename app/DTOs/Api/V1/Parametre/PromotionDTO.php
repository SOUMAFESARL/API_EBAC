<?php

namespace App\DTOs\Api\V1\Parametre;

final readonly class PromotionDTO
{
    /** @param array<string, mixed> $donnees */
    private function __construct(private array $donnees) {}

    /** @param array<string, mixed> $donnees */
    public static function fromArray(array $donnees): self
    {
        foreach (['num_promotion', 'annee_entree', 'id_niveau'] as $champ) {
            if (array_key_exists($champ, $donnees) && $donnees[$champ] !== null) {
                $donnees[$champ] = (int) $donnees[$champ];
            }
        }

        return new self($donnees);
    }

    /** @return array<string, mixed> */
    public function toArray(): array { return $this->donnees; }
}
