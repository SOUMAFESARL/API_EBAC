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

        $this->assertSame('API EBAC', $documentation['info']['title']);
        $this->assertArrayHasKey('get', $documentation['paths']['/eglises']);
        $this->assertArrayHasKey('post', $documentation['paths']['/eglises']);
        $this->assertArrayHasKey('get', $documentation['paths']['/eglises/{id}']);
        $this->assertArrayHasKey('put', $documentation['paths']['/eglises/{id}']);
        $this->assertArrayHasKey('patch', $documentation['paths']['/eglises/{id}']);
        $this->assertArrayHasKey('delete', $documentation['paths']['/eglises/{id}']);
        $this->assertSame('http', $documentation['components']['securitySchemes']['sanctum']['type']);
        $this->assertSame('bearer', $documentation['components']['securitySchemes']['sanctum']['scheme']);

        $parametres = array_column($documentation['paths']['/eglises']['get']['parameters'], 'name');
        foreach (['recherche', 'q', 'eglise', 'ville', 'ville_commune', 'region', 'district', 'denomination', 'pasteur', 'capacite_min', 'avec_etudiants'] as $parametre) {
            $this->assertContains($parametre, $parametres);
        }

        $schemas = $documentation['components']['schemas'];
        $this->assertArrayHasKey('EgliseModificationPayload', $schemas);
        $this->assertArrayHasKey('UtilisateurEglise', $schemas);
        $this->assertArrayHasKey('nombre_etudiants', $schemas['Eglise']['properties']);
        $this->assertSame(
            '#/components/schemas/EgliseModificationPayload',
            $documentation['paths']['/eglises/{id}']['patch']['requestBody']['content']['application/json']['schema']['$ref'],
        );
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
        $this->assertArrayHasKey('get', $documentation['paths']['/parametres/niveaux/{id}']);
        $this->assertArrayHasKey('put', $documentation['paths']['/parametres/niveaux/{id}']);
        $this->assertArrayHasKey('patch', $documentation['paths']['/parametres/niveaux/{id}']);
        $this->assertArrayHasKey('delete', $documentation['paths']['/parametres/niveaux/{id}']);
        $this->assertArrayHasKey('Niveau', $documentation['components']['schemas']);
        $this->assertArrayHasKey('NiveauPayload', $documentation['components']['schemas']);
    }

    public function test_swagger_ui_est_accessible(): void
    {
        $this->get('/api/documentation')->assertOk();
    }

    public function test_swagger_decrit_toutes_les_api_ajoutees(): void
    {
        $documentation = json_decode(file_get_contents(storage_path('api-docs/api-docs.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach (['annees-academiques', 'promotions', 'matieres', 'modules', 'cours'] as $ressource) {
            $collection = "/parametres/{$ressource}";
            $element = "{$collection}/{id}";
            foreach (['get', 'post'] as $methode) {
                $this->assertArrayHasKey($methode, $documentation['paths'][$collection]);
            }
            foreach (['get', 'put', 'patch', 'delete'] as $methode) {
                $this->assertArrayHasKey($methode, $documentation['paths'][$element]);
            }
        }

        $this->assertArrayHasKey('post', $documentation['paths']['/Etudiant/pre-inscription']);
        $this->assertArrayNotHasKey('security', $documentation['paths']['/Etudiant/pre-inscription']['post']);

        foreach (['AnneeAcademique', 'Promotion', 'Matiere', 'Module', 'Cours', 'PreInscriptionPayload', 'PreInscriptionResultat'] as $schema) {
            $this->assertArrayHasKey($schema, $documentation['components']['schemas']);
        }
    }
}
