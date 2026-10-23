<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Get(path: '/parametres/ecole', operationId: 'afficherConfigurationEcole',
    summary: 'Consulter la configuration de l’école', tags: ['Configuration école'], security: [['sanctum' => []]],
    description: 'Tout compte authentifie actif. Configuration unique. Retourne ecole: null avant le premier enregistrement. Les URL des images sont signees et valables 15 minutes.',
    responses: [new OA\Response(response: 200, description: 'Configuration ou null', content: new OA\JsonContent(properties: [new OA\Property(property: 'ecole', ref: '#/components/schemas/ConfigurationEcole', nullable: true)])),
        new OA\Response(response: 401, description: 'Authentification requise'), new OA\Response(response: 403, description: 'Compte inactif')])]
#[OA\Post(path: '/parametres/ecole', operationId: 'enregistrerConfigurationEcole',
    summary: 'Créer ou modifier la configuration et ses images', tags: ['Configuration école'], security: [['sanctum' => []]],
    description: 'ADMIN ou DIRECTION, compte actif. Configuration unique, sans ID. nom_ecole et code_ecole obligatoires au premier enregistrement. Les champs omis sont conserves. Pour supprimer une image, envoyer son champ a null en JSON. POST multipart/form-data pour les fichiers. biographie correspond a l historique de l ecole.',
    requestBody: new OA\RequestBody(required: true, content: [
        new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(ref: '#/components/schemas/ConfigurationEcolePayload')),
        new OA\JsonContent(ref: '#/components/schemas/ConfigurationEcolePayload', example: ['nom_ecole' => 'École Biblique de l’Alliance Chrétienne', 'code_ecole' => 'EBAC', 'ville' => 'Abidjan', 'email' => 'contact@example.com']),
    ]),
    responses: [new OA\Response(response: 201, description: 'Configuration créée', content: new OA\JsonContent(ref: '#/components/schemas/ConfigurationEcoleReponse')),
        new OA\Response(response: 200, description: 'Configuration modifiée', content: new OA\JsonContent(ref: '#/components/schemas/ConfigurationEcoleReponse')),
        new OA\Response(response: 401, description: 'Authentification requise'), new OA\Response(response: 403, description: 'Compte inactif ou rôle interdit'), new OA\Response(response: 422, description: 'Champs ou fichiers invalides')])]
#[OA\Patch(path: '/parametres/ecole', operationId: 'modifierConfigurationEcole',
    summary: 'Modifier les informations de l’école en JSON', tags: ['Configuration école'], security: [['sanctum' => []]],
    description: 'ADMIN ou DIRECTION. Memes regles que POST. Champs omis conserves. Champs facultatifs effacables avec null. Images a envoyer avec POST multipart/form-data.',
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ConfigurationEcolePayload')),
    responses: [new OA\Response(response: 200, description: 'Configuration modifiée', content: new OA\JsonContent(ref: '#/components/schemas/ConfigurationEcoleReponse')),
        new OA\Response(response: 201, description: 'Configuration créée si absente'), new OA\Response(response: 401, description: 'Authentification requise'), new OA\Response(response: 403, description: 'Accès refusé'), new OA\Response(response: 422, description: 'Données invalides')])]
#[OA\Put(path: '/parametres/ecole', operationId: 'mettreAJourConfigurationEcole',
    summary: 'Enregistrer les informations de l’école en JSON', tags: ['Configuration école'], security: [['sanctum' => []]],
    description: 'Alias de PATCH : mise a jour partielle, champs omis conserves. ADMIN ou DIRECTION. Pour les fichiers utiliser POST multipart/form-data.',
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ConfigurationEcolePayload')),
    responses: [new OA\Response(response: 200, description: 'Configuration modifiée', content: new OA\JsonContent(ref: '#/components/schemas/ConfigurationEcoleReponse')),
        new OA\Response(response: 201, description: 'Configuration créée si absente'), new OA\Response(response: 401, description: 'Authentification requise'), new OA\Response(response: 403, description: 'Accès refusé'), new OA\Response(response: 422, description: 'Données invalides')])]
#[OA\Get(path: '/parametres/ecole/fichiers/{type}', operationId: 'afficherFichierConfigurationEcole',
    summary: 'Afficher une image avec son URL signée', tags: ['Configuration école'],
    description: 'Utiliser l URL complete retournee dans logo_url, favicon_url, signature_url ou signature_directeur_url. Signature valable 15 minutes, sans Bearer supplementaire. Aucun chemin de stockage accepte.',
    parameters: [new OA\Parameter(name: 'type', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['logo', 'favicon', 'signature', 'signature_directeur', 'signature_directeur_academique'])),
        new OA\Parameter(name: 'expires', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'signature', in: 'query', required: true, schema: new OA\Schema(type: 'string'))],
    responses: [new OA\Response(response: 200, description: 'Image binaire', content: new OA\MediaType(mediaType: 'image/*', schema: new OA\Schema(type: 'string', format: 'binary'))),
        new OA\Response(response: 403, description: 'URL invalide ou expirée'), new OA\Response(response: 404, description: 'Configuration ou fichier absent')])]
#[OA\Schema(schema: 'ConfigurationEcoleInformations', type: 'object', properties: [
    new OA\Property(property: 'sigle', description: 'Sigle de l’institution, distinct du code_ecole existant.', type: 'string', maxLength: 50, nullable: true),
    new OA\Property(property: 'nom_plateforme', type: 'string', maxLength: 255, nullable: true, example: 'EBAC SIG'),
    new OA\Property(property: 'arrete', description: 'Référence légale de l’établissement.', type: 'string', maxLength: 255, nullable: true),
    new OA\Property(property: 'boite_postale', type: 'string', maxLength: 255, nullable: true, example: 'BP 31 Toumodi'),
    new OA\Property(property: 'site_web', description: 'URL HTTP ou HTTPS.', type: 'string', format: 'uri', maxLength: 500, nullable: true),
    new OA\Property(property: 'directeur_civilite_id', description: 'ID dans civilite, non supprimée.', type: 'integer', nullable: true),
    new OA\Property(property: 'directeur_nom', type: 'string', maxLength: 255, nullable: true),
    new OA\Property(property: 'directeur_prenoms', type: 'string', maxLength: 255, nullable: true),
    new OA\Property(property: 'directeur_academique_civilite_id', description: 'ID dans civilite, non supprimée.', type: 'integer', nullable: true),
    new OA\Property(property: 'directeur_academique_nom', type: 'string', maxLength: 255, nullable: true),
    new OA\Property(property: 'directeur_academique_prenoms', type: 'string', maxLength: 255, nullable: true),
    new OA\Property(property: 'nom_ecole', type: 'string', maxLength: 180),
    new OA\Property(property: 'code_ecole', type: 'string', maxLength: 50),
    new OA\Property(property: 'slogan', type: 'string', maxLength: 255, nullable: true),
    new OA\Property(property: 'adresse', type: 'string', maxLength: 255, nullable: true),
    new OA\Property(property: 'fondateur', type: 'string', maxLength: 255, nullable: true),
    new OA\Property(property: 'directeur_actuel', type: 'string', maxLength: 255, nullable: true),
    new OA\Property(property: 'situation_geographique', type: 'string', maxLength: 255, nullable: true),
    new OA\Property(property: 'ville', type: 'string', maxLength: 255, nullable: true),
    new OA\Property(property: 'region', type: 'string', maxLength: 255, nullable: true),
    new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 150, nullable: true),
    new OA\Property(property: 'telephone', type: 'string', maxLength: 30, nullable: true),
    new OA\Property(property: 'cellulaire', type: 'string', maxLength: 30, nullable: true),
    new OA\Property(property: 'biographie', description: 'Présentation et historique de l’école, texte brut.', type: 'string', maxLength: 50000, nullable: true),
])]
#[OA\Schema(schema: 'ConfigurationEcolePayload', allOf: [
    new OA\Schema(ref: '#/components/schemas/ConfigurationEcoleInformations'),
    new OA\Schema(type: 'object', properties: [
        new OA\Property(property: 'logo', description: 'PNG, JPEG ou WebP, 2 Mo maximum. null pour supprimer.', type: 'string', format: 'binary', nullable: true),
        new OA\Property(property: 'favicon', description: 'ICO, PNG, JPEG ou WebP, 1 Mo maximum. null pour supprimer.', type: 'string', format: 'binary', nullable: true),
        new OA\Property(property: 'signature', description: 'Signature de l’école, PNG, JPEG ou WebP, 2 Mo maximum. null pour supprimer.', type: 'string', format: 'binary', nullable: true),
        new OA\Property(property: 'signature_directeur', description: 'PNG, JPEG ou WebP, 2 Mo maximum. null pour supprimer.', type: 'string', format: 'binary', nullable: true),
        new OA\Property(property: 'signature_directeur_academique', description: 'Signature du directeur académique : PNG, JPEG ou WebP, 2 Mo maximum. null pour supprimer.', type: 'string', format: 'binary', nullable: true),
    ]),
])]
#[OA\Schema(schema: 'ConfigurationEcole', allOf: [
    new OA\Schema(ref: '#/components/schemas/ConfigurationEcoleInformations'),
    new OA\Schema(type: 'object', properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'logo_url', type: 'string', nullable: true),
        new OA\Property(property: 'favicon_url', type: 'string', nullable: true),
        new OA\Property(property: 'signature_url', type: 'string', nullable: true),
        new OA\Property(property: 'signature_directeur_url', type: 'string', nullable: true),
        new OA\Property(property: 'signature_directeur_academique_url', type: 'string', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]),
])]
#[OA\Schema(schema: 'ConfigurationEcoleReponse', type: 'object', properties: [
    new OA\Property(property: 'message', type: 'string'),
    new OA\Property(property: 'ecole', ref: '#/components/schemas/ConfigurationEcole'),
])]
final class ConfigurationEcoleDocumentation {}
