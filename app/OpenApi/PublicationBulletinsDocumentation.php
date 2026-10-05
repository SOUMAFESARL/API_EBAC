<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Get(path: '/administration/bulletins', summary: 'Lister les bulletins à publier',
    tags: ['Publication des bulletins'], security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'id_inscription', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_promotion', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_annee_academique', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'periode', in: 'query', schema: new OA\Schema(type: 'string', maxLength: 50)),
        new OA\Parameter(name: 'statut', in: 'query', schema: new OA\Schema(type: 'string', enum: ['Brouillon', 'Publié'])),
        new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100)),
    ], responses: [new OA\Response(response: 200, description: 'Pagination Laravel avec inscriptions.'),
        new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 403, description: 'Rôle interdit ou compte inactif.'),
        new OA\Response(response: 422, description: 'Filtres invalides.')])]
#[OA\Get(path: '/administration/bulletins/{id}', summary: 'Consulter un bulletin et ses matières',
    tags: ['Publication des bulletins'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'bulletin avec inscription et lignes.'),
        new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 403, description: 'Accès refusé.'),
        new OA\Response(response: 404, description: 'Bulletin introuvable.')])]
#[OA\Post(path: '/administration/bulletins/{id}/publier', summary: 'Publier un bulletin existant',
    description: 'ADMIN, DIRECTION, SECRETARIAT, SECRETAIRE_ACADEMIQUE. Aucun corps requis. Passe un Brouillon à Publié avec date_publication et updated_by. Un bulletin déjà publié conserve sa date et son auteur. Ne génère pas de bulletin et ne recalcule pas les notes.',
    tags: ['Publication des bulletins'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'message et bulletin publié.'),
        new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 403, description: 'Accès refusé.'),
        new OA\Response(response: 404, description: 'Bulletin introuvable.'),
        new OA\Response(response: 422, description: 'Statut incompatible.')])]
final class PublicationBulletinsDocumentation {}
