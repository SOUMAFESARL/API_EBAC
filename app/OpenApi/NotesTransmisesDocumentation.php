<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/administration/notes-transmises', operationId: 'listerNotesTransmisesAdministration',
    summary: 'Consulter les feuilles de notes transmises par les enseignants',
    description: 'Accessible aux roles ADMIN, SECRETARIAT, SECRETAIRE_ACADEMIQUE et DIRECTION. Les brouillons sont exclus. La transmission ne publie pas les notes aux etudiants.',
    tags: ['Administration des notes'], security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'id_annee_academique', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_promotion', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_matiere', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100)),
    ],
    responses: [new OA\Response(response: 200, description: 'feuilles_notes et meta de pagination.'),
        new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 403, description: 'Acces reserve a l administration.'),
        new OA\Response(response: 422, description: 'Filtres invalides.')]
)]
#[OA\Get(
    path: '/administration/notes-transmises/{id}', operationId: 'afficherNotesTransmisesAdministration',
    summary: 'Consulter les notes et les etudiants d une feuille transmise',
    tags: ['Administration des notes'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'feuille_notes avec contexte et notes des etudiants.'),
        new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 403, description: 'Acces reserve a l administration.'),
        new OA\Response(response: 404, description: 'Feuille inexistante ou non transmise.')]
)]
final class NotesTransmisesDocumentation {}
