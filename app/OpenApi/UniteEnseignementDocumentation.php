<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'UniteEnseignementPayload', required: ['libelle', 'code', 'statut'], properties: [
    new OA\Property(property: 'libelle', type: 'string', maxLength: 180, example: 'Connaissance de la Bible'),
    new OA\Property(property: 'code', type: 'string', maxLength: 50, example: 'BIB'),
    new OA\Property(property: 'description', type: 'string', nullable: true),
    new OA\Property(property: 'statut', type: 'string', enum: ['Actif', 'Archive']),
])]
#[OA\Schema(schema: 'UniteEnseignementModification', properties: [
    new OA\Property(property: 'libelle', type: 'string', maxLength: 180),
    new OA\Property(property: 'code', type: 'string', maxLength: 50),
    new OA\Property(property: 'description', type: 'string', nullable: true),
    new OA\Property(property: 'statut', type: 'string', enum: ['Actif', 'Archive']),
])]
#[OA\Tag(name: 'Unités enseignement', description: 'CRUD des UE. Compte actif, rôles ADMIN, SECRETAIRE_ACADEMIQUE, SECRETARIAT ou DIRECTION.')]
#[OA\Get(path: '/parametres/unites-enseignement', operationId: 'listerUnitesEnseignement', tags: ['Unités enseignement'], security: [['sanctum' => []]], parameters: [
    new OA\Parameter(name: 'q', in: 'query', schema: new OA\Schema(type: 'string')),
    new OA\Parameter(name: 'recherche', in: 'query', schema: new OA\Schema(type: 'string')),
    new OA\Parameter(name: 'code', in: 'query', schema: new OA\Schema(type: 'string')),
    new OA\Parameter(name: 'statut', in: 'query', schema: new OA\Schema(type: 'string', enum: ['Actif', 'Archive'])),
], responses: [new OA\Response(response: 200, description: 'Liste dans unites_enseignement')])]
#[OA\Post(path: '/parametres/unites-enseignement', operationId: 'creerUniteEnseignement', tags: ['Unités enseignement'], security: [['sanctum' => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UniteEnseignementPayload')), responses: [new OA\Response(response: 201, description: 'UE créée dans unite_enseignement'), new OA\Response(response: 422, description: 'Validation invalide ou code déjà utilisé')])]
#[OA\Get(path: '/parametres/unites-enseignement/{id}', operationId: 'afficherUniteEnseignement', tags: ['Unités enseignement'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'UE dans unite_enseignement'), new OA\Response(response: 404, description: 'UE introuvable')])]
#[OA\Put(path: '/parametres/unites-enseignement/{id}', operationId: 'mettreAJourUniteEnseignement', tags: ['Unités enseignement'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UniteEnseignementModification')), responses: [new OA\Response(response: 200, description: 'UE modifiée, champs omis conservés'), new OA\Response(response: 404, description: 'UE introuvable'), new OA\Response(response: 422, description: 'Validation invalide')])]
#[OA\Patch(path: '/parametres/unites-enseignement/{id}', operationId: 'modifierUniteEnseignement', tags: ['Unités enseignement'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UniteEnseignementModification')), responses: [new OA\Response(response: 200, description: 'UE modifiée'), new OA\Response(response: 404, description: 'UE introuvable'), new OA\Response(response: 422, description: 'Validation invalide')])]
#[OA\Delete(path: '/parametres/unites-enseignement/{id}', operationId: 'supprimerUniteEnseignement', tags: ['Unités enseignement'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'UE supprimée logiquement'), new OA\Response(response: 404, description: 'UE introuvable')])]
final class UniteEnseignementDocumentation {}
