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

        foreach ([
            ['/eglises', 'get'],
            ['/eglises', 'post'],
            ['/eglises/{id}', 'get'],
            ['/eglises/{id}', 'put'],
            ['/eglises/{id}', 'patch'],
            ['/eglises/{id}', 'delete'],
        ] as [$route, $methode]) {
            $this->assertArrayNotHasKey('security', $documentation['paths'][$route][$methode]);
        }

        $parametres = array_column($documentation['paths']['/eglises']['get']['parameters'], 'name');
        foreach (['recherche', 'q', 'eglise', 'ville', 'ville_commune', 'region', 'district', 'denomination', 'pasteur', 'capacite_min', 'avec_etudiants'] as $parametre) {
            $this->assertContains($parametre, $parametres);
        }

        $schemas = $documentation['components']['schemas'];
        $this->assertArrayHasKey('EgliseModificationPayload', $schemas);
        $this->assertArrayHasKey('UtilisateurEglise', $schemas);
        $this->assertArrayHasKey('StatistiquesEtudiantsEglise', $schemas);
        $this->assertArrayHasKey('EgliseDetail', $schemas);
        $this->assertArrayHasKey('nombre_etudiants', $schemas['Eglise']['properties']);
        $this->assertSame(
            '#/components/schemas/EgliseDetail',
            $documentation['paths']['/eglises/{id}']['get']['responses']['200']['content']['application/json']['schema']['properties']['eglise']['$ref'],
        );
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

        $this->assertArrayHasKey('post', $documentation['paths']['/etudiant/pre-inscription']);
        $this->assertArrayNotHasKey('security', $documentation['paths']['/etudiant/pre-inscription']['post']);
        $this->assertArrayHasKey('multipart/form-data', $documentation['paths']['/etudiant/pre-inscription']['post']['requestBody']['content']);
        $this->assertArrayHasKey('get', $documentation['paths']['/administration/preinscriptions']);
        $this->assertArrayHasKey('get', $documentation['paths']['/administration/preinscriptions/{preinscription}']);
        $this->assertArrayHasKey('post', $documentation['paths']['/administration/preinscriptions/{preinscription}/creer-compte']);
        $this->assertSame(
            [['sanctum' => []]],
            $documentation['paths']['/administration/preinscriptions/{preinscription}/creer-compte']['post']['security'],
        );

        $preInscription = $documentation['components']['schemas']['PreInscriptionPayload'];
        $this->assertContains('eglise_id', $preInscription['required']);
        $this->assertContains('civilite_id', $preInscription['required']);
        $this->assertContains('photo_identite', $preInscription['required']);
        $this->assertArrayNotHasKey('sexe', $preInscription['properties']);
        $this->assertSame('binary', $preInscription['properties']['photo_identite']['format']);
        $this->assertArrayNotHasKey('nullable', $preInscription['properties']['eglise_id']);

        $promotion = $documentation['components']['schemas']['PromotionPayload'];
        $this->assertNotContains('code', $promotion['required']);
        $this->assertArrayNotHasKey('code', $promotion['properties']);
        $this->assertContains('num_promotion', $promotion['required']);
        $this->assertContains('annee_entree', $promotion['required']);
        $this->assertArrayNotHasKey('rang', $promotion['properties']);
        $this->assertArrayNotHasKey('capacite', $promotion['properties']);

        foreach (['AnneeAcademique', 'Promotion', 'Matiere', 'Module', 'Cours', 'PreInscriptionPayload', 'PreInscriptionResultat', 'PreInscriptionAdministration', 'CompteEtudiantCree'] as $schema) {
            $this->assertArrayHasKey($schema, $documentation['components']['schemas']);
        }
    }
}
