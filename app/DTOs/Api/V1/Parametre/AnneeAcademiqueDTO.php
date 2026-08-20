<?php

namespace App\DTOs\Api\V1\Parametre;

final readonly class AnneeAcademiqueDTO
{
    /** @param array<string, mixed> $donnees */
    private function __construct(private array $donnees) {}

    /** @param array<string, mixed> $donnees */
    public static function fromArray(array $donnees): self
    {
        if (array_key_exists('active', $donnees)) {
            $donnees['active'] = (bool) $donnees['active'];
        }

        return new self($donnees);
    }

    /** @return array<string, mixed> */
    public function toArray(): array { return $this->donnees; }
}
