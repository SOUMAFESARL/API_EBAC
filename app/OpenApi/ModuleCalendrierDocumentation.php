<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ModuleCalendrierPayload',
    type: 'object',
    required: ['id_calendrier', 'libelle', 'ordre', 'date_debut', 'date_fin'],
    properties: [
        new OA\Property(property: 'id_calendrier', description: 'Identifiant du calendrier académique.', type: 'integer', minimum: 1, example: 1),
        new OA\Property(property: 'libelle', type: 'string', maxLength: 180, example: 'Premier semestre'),
        new OA\Property(property: 'ordre', description: 'Ordre unique dans le calendrier.', type: 'integer', minimum: 1, maximum: 65535, example: 1),
        new OA\Property(property: 'date_debut', type: 'string', format: 'date', example: '2026-09-01'),
        new OA\Property(property: 'date_fin', type: 'string', format: 'date', example: '2026-12-20'),
    ]
)]
#[OA\Schema(
    schema: 'ModuleCalendrierModification',
    description: 'Tous les champs sont facultatifs avec PATCH.',
    type: 'object',
    properties: [
        new OA\Property(property: 'id_calendrier', type: 'integer', minimum: 1, example: 1),
        new OA\Property(property: 'libelle', type: 'string', maxLength: 180, example: 'Semestre 1'),
        new OA\Property(property: 'ordre', type: 'integer', minimum: 1, maximum: 65535, example: 1),
        new OA\Property(property: 'date_debut', type: 'string', format: 'date', example: '2026-09-01'),
        new OA\Property(property: 'date_fin', type: 'string', format: 'date', example: '2026-12-20'),
    ]
)]
#[OA\Schema(
    schema: 'ModuleCalendrier',
    type: 'object',
    required: ['id', 'id_calendrier', 'libelle', 'ordre', 'date_debut', 'date_fin'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', readOnly: true, example: 1),
        new OA\Property(property: 'id_calendrier', type: 'integer', example: 1),
        new OA\Property(property: 'libelle', type: 'string', example: 'Premier semestre'),
        new OA\Property(property: 'ordre', type: 'integer', example: 1),
        new OA\Property(property: 'date_debut', type: 'string', format: 'date', example: '2026-09-01'),
        new OA\Property(property: 'date_fin', type: 'string', format: 'date', example: '2026-12-20'),
        new OA\Property(property: 'calendrier', type: 'object', nullable: true, readOnly: true),
        new OA\Property(property: 'evenements', type: 'array', readOnly: true, items: new OA\Items(type: 'object')),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', readOnly: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', readOnly: true),
    ]
)]
#[OA\Get(
    path: '/parametres/modules-calendrier',
    operationId: 'listerModulesCalendrier',
    summary: 'Lister les modules du calendrier',
    tags: ['Modules calendrier'],
    security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'id_calendrier', description: 'Filtrer les modules par calendrier.', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1))],
    responses: [
        new OA\Response(response: 200, description: 'Liste triée par ordre.', content: new OA\JsonContent(type: 'object', required: ['modules'], properties: [new OA\Property(property: 'modules', type: 'array', items: new OA\Items(ref: '#/components/schemas/ModuleCalendrier'))])),
        new OA\Response(response: 401, description: 'Authentification requise', content: new OA\JsonContent(ref: '#/components/schemas/ErreurAuthentification')),
        new OA\Response(response: 403, description: 'Rôle non autorisé'),
        new OA\Response(response: 422, description: 'Filtre invalide', content: new OA\JsonContent(ref: '#/components/schemas/ErreurValidation')),
    ]
)]
#[OA\Post(
    path: '/parametres/modules-calendrier',
    operationId: 'creerModuleCalendrier',
    summary: 'Créer un module de calendrier',
    description: 'La période doit appartenir à l’année académique, ne chevaucher aucun autre module et son ordre doit être unique dans le calendrier.',
    tags: ['Modules calendrier'],
    security: [['sanctum' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ModuleCalendrierPayload')),
    responses: [
        new OA\Response(response: 201, description: 'Module créé.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'message', type: 'string', example: 'Module créé avec succès.'), new OA\Property(property: 'module', ref: '#/components/schemas/ModuleCalendrier')])),
        new OA\Response(response: 401, description: 'Authentification requise'),
        new OA\Response(response: 403, description: 'Rôle non autorisé'),
        new OA\Response(response: 422, description: 'Données invalides', content: new OA\JsonContent(ref: '#/components/schemas/ErreurValidation')),
    ]
)]
#[OA\Get(
    path: '/parametres/modules-calendrier/{id}', operationId: 'afficherModuleCalendrier', summary: 'Afficher un module de calendrier', tags: ['Modules calendrier'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1))],
    responses: [new OA\Response(response: 200, description: 'Module trouvé.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'module', ref: '#/components/schemas/ModuleCalendrier')])), new OA\Response(response: 401, description: 'Authentification requise'), new OA\Response(response: 403, description: 'Rôle non autorisé'), new OA\Response(response: 404, description: 'Module introuvable')]
)]
#[OA\Put(
    path: '/parametres/modules-calendrier/{id}', operationId: 'remplacerModuleCalendrier', summary: 'Remplacer complètement un module de calendrier', tags: ['Modules calendrier'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ModuleCalendrierPayload')),
    responses: [new OA\Response(response: 200, description: 'Module modifié.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'message', type: 'string'), new OA\Property(property: 'module', ref: '#/components/schemas/ModuleCalendrier')])), new OA\Response(response: 401, description: 'Authentification requise'), new OA\Response(response: 403, description: 'Rôle non autorisé'), new OA\Response(response: 404, description: 'Module introuvable'), new OA\Response(response: 422, description: 'Données invalides', content: new OA\JsonContent(ref: '#/components/schemas/ErreurValidation'))]
)]
#[OA\Patch(
    path: '/parametres/modules-calendrier/{id}', operationId: 'modifierModuleCalendrier', summary: 'Modifier partiellement un module de calendrier', tags: ['Modules calendrier'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ModuleCalendrierModification')),
    responses: [new OA\Response(response: 200, description: 'Module modifié.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'message', type: 'string'), new OA\Property(property: 'module', ref: '#/components/schemas/ModuleCalendrier')])), new OA\Response(response: 401, description: 'Authentification requise'), new OA\Response(response: 403, description: 'Rôle non autorisé'), new OA\Response(response: 404, description: 'Module introuvable'), new OA\Response(response: 422, description: 'Données invalides', content: new OA\JsonContent(ref: '#/components/schemas/ErreurValidation'))]
)]
#[OA\Delete(
    path: '/parametres/modules-calendrier/{id}', operationId: 'supprimerModuleCalendrier', summary: 'Supprimer un module de calendrier', description: 'Les événements associés sont supprimés en cascade. La suppression est refusée si un créneau utilise le module.', tags: ['Modules calendrier'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1))],
    responses: [new OA\Response(response: 200, description: 'Module supprimé.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'message', type: 'string', example: 'Module supprimé avec succès.')])), new OA\Response(response: 401, description: 'Authentification requise'), new OA\Response(response: 403, description: 'Rôle non autorisé'), new OA\Response(response: 404, description: 'Module introuvable'), new OA\Response(response: 422, description: 'Module utilisé par un créneau', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'message', type: 'string', example: 'Ce module est utilisé par des créneaux et ne peut pas être supprimé.')]))]
)]
final class ModuleCalendrierDocumentation {}
