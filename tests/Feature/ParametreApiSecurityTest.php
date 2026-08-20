<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParametreApiSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_nouveaux_crud_exigent_une_authentification(): void
    {
        foreach (['annees-academiques', 'promotions', 'matieres', 'modules', 'cours'] as $ressource) {
            $this->getJson("/api/v1/parametres/{$ressource}")->assertUnauthorized();
            $this->postJson("/api/v1/parametres/{$ressource}", [])->assertUnauthorized();
            $this->getJson("/api/v1/parametres/{$ressource}/999")->assertUnauthorized();
            $this->patchJson("/api/v1/parametres/{$ressource}/999", [])->assertUnauthorized();
            $this->deleteJson("/api/v1/parametres/{$ressource}/999")->assertUnauthorized();
        }
    }

    public function test_la_pre_inscription_ne_demande_pas_d_authentification(): void
    {
        $this->postJson('/api/v1/Etudiant/pre-inscription', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nom', 'prenoms', 'email', 'telephone']);
    }
}
