<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'SallePayload', type: 'object', required: ['nom', 'code'], properties: [
    new OA\Property(property: 'nom', type: 'string', maxLength: 180, example: 'Salle A'),
    new OA\Property(property: 'code', description: 'Normalis? en majuscules, unique parmi les salles non supprim?es.', type: 'string', maxLength: 30, example: 'A'),
    new OA\Property(property: 'statut', type: 'string', enum: ['Actif', 'Inactif'], example: 'Actif'),
])]
#[OA\Schema(schema: 'SalleModification', type: 'object', properties: [
    new OA\Property(property: 'nom', type: 'string', maxLength: 180),
    new OA\Property(property: 'code', type: 'string', maxLength: 30),
    new OA\Property(property: 'statut', type: 'string', enum: ['Actif', 'Inactif']),
])]
#[OA\Schema(schema: 'Salle', type: 'object', allOf: [new OA\Schema(ref: '#/components/schemas/SallePayload')], properties: [
    new OA\Property(property: 'id', type: 'integer', readOnly: true),
    new OA\Property(property: 'created_by', type: 'integer', nullable: true, readOnly: true),
    new OA\Property(property: 'updated_by', type: 'integer', nullable: true, readOnly: true),
    new OA\Property(property: 'deleted_by', type: 'integer', nullable: true, readOnly: true),
    new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
    new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
    new OA\Property(property: 'deleted_at', type: 'string', format: 'date-time', nullable: true),
    new OA\Property(property: 'seances_par_semaine', description: 'Toujours null : gestion des s?ances non encore disponible.', type: 'integer', nullable: true),
])]
#[OA\Get(path: '/parametres/salles', operationId: 'listerSalles', summary: 'Liste pagin?e des salles', tags: ['Salles'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'recherche', in: 'query', schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'statut', in: 'query', schema: new OA\Schema(type: 'string', enum: ['Actif', 'Inactif'])), new OA\Parameter(name: 'tri', in: 'query', schema: new OA\Schema(type: 'string', enum: ['code', 'nom', 'statut'], default: 'code')), new OA\Parameter(name: 'direction', in: 'query', schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'], default: 'asc')), new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, default: 1)), new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 15))],
    responses: [new OA\Response(response: 200, description: 'Succ?s', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'salles', type: 'array', items: new OA\Items(ref: '#/components/schemas/Salle')), new OA\Property(property: 'salles_en_service', description: 'Nombre global de salles actives, ind?pendant des filtres.', type: 'integer'), new OA\Property(property: 'meta', type: 'object', properties: [new OA\Property(property: 'current_page', type: 'integer'), new OA\Property(property: 'last_page', type: 'integer'), new OA\Property(property: 'per_page', type: 'integer'), new OA\Property(property: 'total', type: 'integer'), new OA\Property(property: 'from', type: 'integer', nullable: true), new OA\Property(property: 'to', type: 'integer', nullable: true)])])), new OA\Response(response: 401, description: 'Authentification requise'), new OA\Response(response: 403, description: 'Acc?s r?serv? aux comptes actifs ADMIN, SECRETAIRE_ACADEMIQUE et DIRECTION'), new OA\Response(response: 422, description: 'Validation invalide', content: new OA\JsonContent(ref: '#/components/schemas/ErreurValidation'))],
)]
#[OA\Post(path: '/parametres/salles', operationId: 'creerSalle', summary: 'Cr?er une salle', tags: ['Salles'], security: [['sanctum' => []]], parameters: [],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/SallePayload')),
    responses: [new OA\Response(response: 201, description: 'Succ?s', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'message', type: 'string'), new OA\Property(property: 'salle', ref: '#/components/schemas/Salle')])), new OA\Response(response: 401, description: 'Authentification requise'), new OA\Response(response: 403, description: 'Acc?s r?serv? aux comptes actifs ADMIN, SECRETAIRE_ACADEMIQUE et DIRECTION'), new OA\Response(response: 422, description: 'Validation invalide', content: new OA\JsonContent(ref: '#/components/schemas/ErreurValidation'))],
)]
#[OA\Get(path: '/parametres/salles/{id}', operationId: 'afficherSalle', summary: 'Afficher une salle', tags: ['Salles'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1))],
    responses: [new OA\Response(response: 200, description: 'Succ?s', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'salle', ref: '#/components/schemas/Salle')])), new OA\Response(response: 401, description: 'Authentification requise'), new OA\Response(response: 403, description: 'Acc?s r?serv? aux comptes actifs ADMIN, SECRETAIRE_ACADEMIQUE et DIRECTION'), new OA\Response(response: 404, description: 'Salle introuvable')],
)]
#[OA\Put(path: '/parametres/salles/{id}', operationId: 'remplacerSalle', summary: 'Modifier une salle (nom et code obligatoires)', tags: ['Salles'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1))],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/SallePayload')),
    responses: [new OA\Response(response: 200, description: 'Succ?s', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'message', type: 'string'), new OA\Property(property: 'salle', ref: '#/components/schemas/Salle')])), new OA\Response(response: 401, description: 'Authentification requise'), new OA\Response(response: 403, description: 'Acc?s r?serv? aux comptes actifs ADMIN, SECRETAIRE_ACADEMIQUE et DIRECTION'), new OA\Response(response: 404, description: 'Salle introuvable'), new OA\Response(response: 422, description: 'Validation invalide', content: new OA\JsonContent(ref: '#/components/schemas/ErreurValidation'))],
)]
#[OA\Patch(path: '/parametres/salles/{id}', operationId: 'modifierSalle', summary: 'Modifier partiellement une salle', tags: ['Salles'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1))],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/SalleModification')),
    responses: [new OA\Response(response: 200, description: 'Succ?s', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'message', type: 'string'), new OA\Property(property: 'salle', ref: '#/components/schemas/Salle')])), new OA\Response(response: 401, description: 'Authentification requise'), new OA\Response(response: 403, description: 'Acc?s r?serv? aux comptes actifs ADMIN, SECRETAIRE_ACADEMIQUE et DIRECTION'), new OA\Response(response: 404, description: 'Salle introuvable'), new OA\Response(response: 422, description: 'Validation invalide', content: new OA\JsonContent(ref: '#/components/schemas/ErreurValidation'))],
)]
#[OA\Delete(path: '/parametres/salles/{id}', operationId: 'supprimerSalle', summary: 'Supprimer logiquement une salle', tags: ['Salles'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1))],
    responses: [new OA\Response(response: 200, description: 'Succ?s', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'message', type: 'string')])), new OA\Response(response: 401, description: 'Authentification requise'), new OA\Response(response: 403, description: 'Acc?s r?serv? aux comptes actifs ADMIN, SECRETAIRE_ACADEMIQUE et DIRECTION'), new OA\Response(response: 404, description: 'Salle introuvable')],
)]
final class SalleDocumentation {}
