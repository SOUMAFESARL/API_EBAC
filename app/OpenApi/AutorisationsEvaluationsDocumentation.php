<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Get(path: '/administration/autorisations-evaluations', summary: 'Lister les absences pour autoriser une évaluation',
    tags: ['Autorisations des évaluations'], security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'id_etudiant', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_seance', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'autorisee', in: 'query', schema: new OA\Schema(type: 'integer', enum: [0, 1])),
        new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100)),
    ], responses: [new OA\Response(response: 200, description: 'Pagination Laravel des absences avec étudiant, feuille et séance.'),
        new OA\Response(response: 401, description: 'Authentification requise.'), new OA\Response(response: 403, description: 'Accès refusé.'),
        new OA\Response(response: 422, description: 'Filtres invalides.')])]
#[OA\Get(path: '/administration/autorisations-evaluations/{id}', summary: 'Consulter une absence et son autorisation',
    tags: ['Autorisations des évaluations'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Identifiant de la ligne presences.', schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'absence.'), new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 403, description: 'Accès refusé.'), new OA\Response(response: 404, description: 'Absence inaccessible.')])]
#[OA\Post(path: '/administration/autorisations-evaluations/{id}/autoriser', summary: 'Autoriser un étudiant absent à être évalué',
    description: 'ADMIN, DIRECTION, SECRETARIAT, SECRETAIRE_ACADEMIQUE. Absence sur séance réalisée avec présence validée requise. Auteur, date et motif conservés. Toutes les absences concernées doivent être autorisées pour la saisie par cours ou matière. Ne rouvre pas une feuille transmise et ne modifie pas les présences.',
    tags: ['Autorisations des évaluations'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Identifiant de la ligne presences.', schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['motif'], properties: [
        new OA\Property(property: 'motif', type: 'string', maxLength: 5000, example: 'Justificatif accepté'),
    ])), responses: [new OA\Response(response: 200, description: 'message et absence avec evaluation_autorisee_par, evaluation_autorisee_le, motif_autorisation_evaluation.'),
        new OA\Response(response: 401, description: 'Authentification requise.'), new OA\Response(response: 403, description: 'Accès refusé.'),
        new OA\Response(response: 404, description: 'Présence introuvable.'), new OA\Response(response: 422, description: 'Motif ou présence invalide.')])]
final class AutorisationsEvaluationsDocumentation {}
