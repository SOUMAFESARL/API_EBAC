<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Post(path: '/etudiant/reclamations-notes', summary: 'Contester une note de mon bulletin publié',
    description: 'id_ligne_bulletin est bulletin.matieres[].id dans GET /etudiant/bulletins/{id}. Motif obligatoire. Note non nulle, bulletin publié et appartenant à l’étudiant connecté requis. Une seule réclamation en attente par note. Ne modifie pas les notes.',
    tags: ['Réclamations des notes'], security: [['sanctum' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['id_ligne_bulletin', 'motif'], properties: [
        new OA\Property(property: 'id_ligne_bulletin', type: 'integer'),
        new OA\Property(property: 'motif', type: 'string', maxLength: 5000),
    ])), responses: [new OA\Response(response: 201, description: 'reclamation en_attente avec note_contestee.'), new OA\Response(response: 404, description: 'Note inaccessible.'), new OA\Response(response: 422, description: 'Motif invalide, note manquante ou doublon.')])]
#[OA\Get(path: '/etudiant/reclamations-notes', summary: 'Lister mes réclamations', tags: ['Réclamations des notes'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'statut', in: 'query', schema: new OA\Schema(type: 'string', enum: ['en_attente', 'acceptee', 'rejetee'])), new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100))],
    responses: [new OA\Response(response: 200, description: 'Pagination Laravel des réclamations de l’étudiant connecté.')])]
#[OA\Get(path: '/etudiant/reclamations-notes/{id}', summary: 'Consulter ma réclamation et sa réponse', tags: ['Réclamations des notes'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'reclamation.'), new OA\Response(response: 404, description: 'Réclamation inaccessible.')])]
#[OA\Get(path: '/administration/reclamations-notes', summary: 'Lister les réclamations des étudiants', tags: ['Réclamations des notes'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'statut', in: 'query', schema: new OA\Schema(type: 'string', enum: ['en_attente', 'acceptee', 'rejetee'])), new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100))],
    responses: [new OA\Response(response: 200, description: 'Pagination Laravel. ADMIN, SECRETARIAT, SECRETAIRE_ACADEMIQUE, DIRECTION.')])]
#[OA\Get(path: '/administration/reclamations-notes/{id}', summary: 'Consulter une réclamation', tags: ['Réclamations des notes'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'reclamation.'), new OA\Response(response: 404, description: 'Introuvable.')])]
#[OA\Post(path: '/administration/reclamations-notes/{id}/traiter', summary: 'Accepter ou rejeter une réclamation avec réponse',
    description: 'Traitement unique avec acteur et date conservés. Accepter ne modifie aucune note et ne remplace pas le circuit de correction autorisée.',
    tags: ['Réclamations des notes'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['statut', 'reponse'], properties: [
        new OA\Property(property: 'statut', type: 'string', enum: ['acceptee', 'rejetee']), new OA\Property(property: 'reponse', type: 'string', maxLength: 5000),
    ])), responses: [new OA\Response(response: 200, description: 'Réclamation traitée.'), new OA\Response(response: 422, description: 'Réponse invalide ou demande déjà traitée.')])]
final class ReclamationsNotesDocumentation {}
