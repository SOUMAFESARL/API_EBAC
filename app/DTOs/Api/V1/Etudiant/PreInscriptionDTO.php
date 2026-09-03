<?php

namespace App\DTOs\Api\V1\Etudiant;

final readonly class PreInscriptionDTO
{
    /** @param array<string, mixed> $donnees */
    private function __construct(private array $donnees) {}

    /** @param array<string, mixed> $donnees */
    public static function fromArray(array $donnees): self
    {
        if (isset($donnees['eglise_id'])) {
            $donnees['eglise_id'] = (int) $donnees['eglise_id'];
        }
        if (isset($donnees['civilite_id'])) {
            $donnees['civilite_id'] = (int) $donnees['civilite_id'];
        }
        if (isset($donnees['nombre_enfant'])) {
            $donnees['nombre_enfant'] = (int) $donnees['nombre_enfant'];
        }

        return new self($donnees);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->donnees;
    }
}
