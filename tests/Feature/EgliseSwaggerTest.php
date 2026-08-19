<?php

namespace Tests\Feature;

use Tests\TestCase;

class EgliseSwaggerTest extends TestCase
{
    public function test_la_documentation_swagger_decrit_tout_le_crud_des_eglises(): void
    {
        $chemin = storage_path('api-docs/api-docs.json');

        $this->assertFileExists($chemin);

        $documentation = json_decode(file_get_contents($chemin), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('API EBAC — Églises et niveaux', $documentation['info']['title']);
        $this->assertArrayHasKey('get', $documentation['paths']['/eglises']);
        $this->assertArrayHasKey('post', $documentation['paths']['/eglises']);
        $this->assertArrayHasKey('get', $documentation['paths']['/eglises/{eglise}']);
        $this->assertArrayHasKey('patch', $documentation['paths']['/eglises/{eglise}']);
        $this->assertArrayHasKey('delete', $documentation['paths']['/eglises/{eglise}']);
        $this->assertSame('http', $documentation['components']['securitySchemes']['sanctum']['type']);
        $this->assertSame('bearer', $documentation['components']['securitySchemes']['sanctum']['scheme']);
    }

    public function test_la_documentation_swagger_decrit_tout_le_crud_des_niveaux(): void
    {
        $documentation = json_decode(
            file_get_contents(storage_path('api-docs/api-docs.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertArrayHasKey('get', $documentation['paths']['/parametres/niveaux']);
        $this->assertArrayHasKey('post', $documentation['paths']['/parametres/niveaux']);
        $this->assertArrayHasKey('get', $documentation['paths']['/parametres/niveaux/{niveau}']);
        $this->assertArrayHasKey('patch', $documentation['paths']['/parametres/niveaux/{niveau}']);
        $this->assertArrayHasKey('delete', $documentation['paths']['/parametres/niveaux/{niveau}']);
        $this->assertArrayHasKey('Niveau', $documentation['components']['schemas']);
        $this->assertArrayHasKey('NiveauPayload', $documentation['components']['schemas']);
    }

    public function test_swagger_ui_est_accessible(): void
    {
        $this->get('/api/documentation')->assertOk();
    }
}
