<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'MatiereTerminee', type: 'object', nullable: true, properties: [
    new OA\Property(property: 'id', type: 'integer'),
    new OA\Property(property: 'le', type: 'string', format: 'date-time'),
    new OA\Property(property: 'par', type: 'string', nullable: true, description: 'Nom et prénoms de l’auteur.'),
])]
#[OA\Get(path: '/administration/matieres-terminees', operationId: 'listerPromotionsMatieresTerminees', summary: 'Promotions dont on peut terminer les matières',
    description: 'Un de ces droits requis : progression.matieres-terminees.consulter, .terminer ou .rouvrir. ADMIN passe toujours. Promotions encore dans le cursus, avec leur niveau actuel.',
    tags: ['Matières terminées'], security: [['sanctum' => []]],
    responses: [new OA\Response(response: 200, description: 'promotions[] : id, code, num_promotion, annee_entree, niveau.'),
        new OA\Response(response: 401, description: 'Authentification requise.'), new OA\Response(response: 403, description: 'Droit requis.')])]
#[OA\Get(path: '/administration/matieres-terminees/{promotion}', operationId: 'etatMatieresTerminees', summary: 'Matières du niveau de la promotion et leur fin',
    description: 'Matières actives du niveau actuel de la promotion. terminee est null tant que la matière n’est pas terminée : l’étudiant la voit alors « En cours » sur son relevé et son bulletin.',
    tags: ['Matières terminées'], security: [['sanctum' => []]], parameters: [new OA\PathParameter(name: 'promotion', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'promotion, annee_academique, matieres[] (id, code, libelle, enseignant, terminee).', content: new OA\JsonContent(type: 'object', properties: [
        new OA\Property(property: 'matieres', type: 'array', items: new OA\Items(type: 'object', properties: [
            new OA\Property(property: 'id', type: 'integer'), new OA\Property(property: 'libelle', type: 'string'),
            new OA\Property(property: 'terminee', ref: '#/components/schemas/MatiereTerminee'),
        ])),
    ])), new OA\Response(response: 403, description: 'Droit requis.'), new OA\Response(response: 404, description: 'Promotion introuvable.')])]
#[OA\Post(path: '/administration/matieres-terminees', operationId: 'terminerMatiere', summary: 'Marquer une matière terminée pour une promotion',
    description: 'Droit requis : progression.matieres-terminees.terminer. Idempotent : 201 à la création, 200 si déjà terminée. La matière doit être au niveau actuel de la promotion.',
    tags: ['Matières terminées'], security: [['sanctum' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object', required: ['id_matiere', 'id_promotion'], properties: [
        new OA\Property(property: 'id_matiere', type: 'integer'), new OA\Property(property: 'id_promotion', type: 'integer'),
    ])),
    responses: [new OA\Response(response: 201, description: 'Matière terminée : message, terminee.'), new OA\Response(response: 200, description: 'Déjà terminée.'),
        new OA\Response(response: 403, description: 'Droit requis.'), new OA\Response(response: 409, description: 'PROMOTION_CLOSE.'),
        new OA\Response(response: 422, description: 'NIVEAU_DIFFERENT ou champs invalides.')])]
#[OA\Delete(path: '/administration/matieres-terminees/{id}', operationId: 'rouvrirMatiere', summary: 'Rouvrir une matière terminée',
    description: 'Droit requis : progression.matieres-terminees.rouvrir. Refusé (409 PASSAGE_EFFECTUE) une fois la promotion passée au niveau suivant.',
    tags: ['Matières terminées'], security: [['sanctum' => []]], parameters: [new OA\PathParameter(name: 'id', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Matière rouverte.'), new OA\Response(response: 403, description: 'Droit requis.'),
        new OA\Response(response: 404, description: 'Introuvable.'), new OA\Response(response: 409, description: 'PASSAGE_EFFECTUE.')])]
#[OA\Post(path: '/enseignant/mes-cours/{matiere}/terminer', operationId: 'terminerMaMatiere', summary: 'L’enseignant affecté marque sa matière terminée',
    description: 'Rôle ENSEIGNANT avec une affectation en cours sur la matière. Même règle et même réponse que POST /administration/matieres-terminees ; l’enseignant ne rouvre pas.',
    tags: ['Enseignant - Mes cours'], security: [['sanctum' => []]], parameters: [new OA\PathParameter(name: 'matiere', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object', required: ['id_promotion'], properties: [new OA\Property(property: 'id_promotion', type: 'integer')])),
    responses: [new OA\Response(response: 201, description: 'Matière terminée.'), new OA\Response(response: 200, description: 'Déjà terminée.'),
        new OA\Response(response: 404, description: 'Matière non affectée.'), new OA\Response(response: 409, description: 'PROMOTION_CLOSE.'),
        new OA\Response(response: 422, description: 'NIVEAU_DIFFERENT.')])]
class MatieresTermineesDocumentation {}
