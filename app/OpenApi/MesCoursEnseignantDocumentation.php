<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/enseignant/mes-cours',
    operationId: 'listerMesCoursEnseignant',
    summary: 'Lister les matières et cours affectés à l’enseignant connecté',
    description: 'Les affectations sont indépendantes de l’année académique. Le filtre année ne concerne que les promotions, créneaux et indicateurs de progression. Les matières affectées restent consultables sans année configurée.',
    tags: ['Espace enseignant'], security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'id_annee_academique', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'recherche', in: 'query', schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'id_niveau', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_promotion', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_module_calendrier', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100)),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Liste paginée des matières affectées.'),
        new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 403, description: 'Rôle enseignant requis.'),
        new OA\Response(response: 422, description: 'Filtres invalides.'),
    ]
)]
#[OA\Get(
    path: '/enseignant/mes-cours/{matiere}',
    operationId: 'afficherMonCoursEnseignant',
    summary: 'Afficher les modules et cours d’une matière affectée',
    tags: ['Espace enseignant'], security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'matiere', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_annee_academique', in: 'query', schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Détail de la matière, modules, cours et avancement.'),
        new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 403, description: 'Rôle enseignant requis.'),
        new OA\Response(response: 404, description: 'Matière non affectée à cet enseignant.'),
    ]
)]
final class MesCoursEnseignantDocumentation {}
