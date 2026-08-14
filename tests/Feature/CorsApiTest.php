<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsApiTest extends TestCase
{
    public function test_une_requete_preflight_est_acceptee_pour_tout_frontend(): void
    {
        $this->call('OPTIONS', '/api/v1/auth/connexion', [], [], [], [
            'HTTP_ORIGIN' => 'https://frontend.example.com',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type, authorization',
        ])->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertHeader('Access-Control-Allow-Methods')
            ->assertHeader('Access-Control-Allow-Headers');
    }
}
