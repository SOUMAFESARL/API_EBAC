<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/programme', operationId: 'consulterProgrammePublie',
    summary: 'Consulter le programme publié',
    description: 'Portail commun du secrétariat académique, gestionnaire, étudiant et enseignant. Aucun créneau n’est retourné avant publication. Les enseignants voient leurs créneaux et les étudiants ceux de leur promotion/niveau.',
    tags: ['Publication du programme'], security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'id_annee_academique', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_module_calendrier', in: 'query', schema: new OA\Schema(type: 'integer')),
    ],
    responses: [new OA\Response(response: 200, description: 'Programme publié ou liste vide si non publié.'), new OA\Response(response: 401, description: 'Authentification requise.'), new OA\Response(response: 403, description: 'Rôle non autorisé.'), new OA\Response(response: 422, description: 'Filtres invalides.')]
)]
#[OA\Get(
    path: '/administration/publication-programme', operationId: 'etatPublicationProgramme',
    summary: 'Consulter l’état de publication des programmes', tags: ['Publication du programme'], security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'id_annee_academique', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_module_calendrier', in: 'query', schema: new OA\Schema(type: 'integer')),
    ],
    responses: [new OA\Response(response: 200, description: 'État, version et indicateurs du programme.'), new OA\Response(response: 401, description: 'Authentification requise.'), new OA\Response(response: 403, description: 'Rôle ADMIN requis.'), new OA\Response(response: 422, description: 'Filtres invalides.')]
)]
#[OA\Post(
    path: '/administration/publication-programme/{module_id}/publier', operationId: 'publierProgramme',
    summary: 'Publier un programme pour les espaces utilisateur', tags: ['Publication du programme'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'module_id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Programme publié et version incrémentée.'), new OA\Response(response: 401, description: 'Authentification requise.'), new OA\Response(response: 403, description: 'Rôle ADMIN requis.'), new OA\Response(response: 404, description: 'Module introuvable.'), new OA\Response(response: 422, description: 'Aucun créneau à publier.')]
)]
#[OA\Post(
    path: '/administration/publication-programme/{module_id}/retire', operationId: 'retirerProgramme',
    summary: 'Retirer un programme des espaces utilisateur', tags: ['Publication du programme'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'module_id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Programme retiré et immédiatement masqué.'), new OA\Response(response: 401, description: 'Authentification requise.'), new OA\Response(response: 403, description: 'Rôle ADMIN requis.'), new OA\Response(response: 404, description: 'Module introuvable.'), new OA\Response(response: 422, description: 'Programme non publié.')]
)]
final class PublicationProgrammeDocumentation {}
