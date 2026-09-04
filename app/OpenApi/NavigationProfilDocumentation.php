<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Navigation', description: 'Navigation autorisée pour l’utilisateur connecté.')]
#[OA\Tag(name: 'Profil administratif', description: 'Consultation et modification du profil connecté.')]
#[OA\Get(path: '/navigation/sidebar', tags: ['Navigation'], security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'Menus et actions autorisés'), new OA\Response(response: 401, description: 'Non authentifié'), new OA\Response(response: 403, description: 'Compte inactif')])]
#[OA\Get(path: '/utilisateurs/{compte}/photo', tags: ['Comptes'], parameters: [new OA\PathParameter(name: 'compte', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Fichier de la photo du compte'), new OA\Response(response: 404, description: 'Photo ou compte introuvable')])]
#[OA\Get(path: '/administration/profil', tags: ['Profil administratif'], security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'Profil connecté'), new OA\Response(response: 401, description: 'Non authentifié'), new OA\Response(response: 403, description: 'Compte inactif')])]
#[OA\Get(path: '/administration/profil/edit', tags: ['Profil administratif'], security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'Profil et données du formulaire de modification'), new OA\Response(response: 401, description: 'Non authentifié')])]
#[OA\Put(path: '/administration/profil', tags: ['Profil administratif'], security: [['sanctum' => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object')), responses: [new OA\Response(response: 200, description: 'Profil modifié'), new OA\Response(response: 401, description: 'Non authentifié'), new OA\Response(response: 422, description: 'Données invalides')])]
#[OA\Patch(path: '/administration/profil', tags: ['Profil administratif'], security: [['sanctum' => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object')), responses: [new OA\Response(response: 200, description: 'Profil modifié'), new OA\Response(response: 401, description: 'Non authentifié'), new OA\Response(response: 422, description: 'Données invalides')])]
#[OA\Post(path: '/administration/profil', tags: ['Profil administratif'], description: 'Modification multipart/form-data.', security: [['sanctum' => []]], requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object'))), responses: [new OA\Response(response: 200, description: 'Profil modifié'), new OA\Response(response: 401, description: 'Non authentifié'), new OA\Response(response: 422, description: 'Données invalides')])]
final class NavigationProfilDocumentation
{
}
