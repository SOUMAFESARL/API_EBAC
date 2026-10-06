<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/administration/notes-transmises', operationId: 'listerNotesTransmisesAdministration',
    summary: 'Consulter les feuilles de notes transmises par les enseignants',
    description: 'Accessible aux roles ADMIN, SECRETARIAT, SECRETAIRE_ACADEMIQUE et DIRECTION. Circuit : transmise → validee_secretariat → transmise_direction → validee_direction. Un refus du secretariat (rejetee_secretariat) rouvre la saisie enseignant et exige une nouvelle transmission. Un rejet de la direction (rejetee_direction) exige un reexamen du secretariat, qui peut valider a nouveau ou renvoyer a l enseignant. Les brouillons sont exclus. Ces validations ne publient pas les notes aux etudiants.',
    tags: ['Administration des notes'], security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'id_annee_academique', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_promotion', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_matiere', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'statut', in: 'query', schema: new OA\Schema(type: 'string', enum: ['transmise', 'validee_secretariat', 'rejetee_secretariat', 'transmise_direction', 'validee_direction', 'rejetee_direction'])),
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
    responses: [new OA\Response(response: 200, description: 'feuille_notes avec contexte, notes des etudiants et historique : action, statut_avant, statut_apres, motif, created_at et acteur.'),
        new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 403, description: 'Acces reserve a l administration.'),
        new OA\Response(response: 404, description: 'Feuille inexistante ou non transmise.')]
)]
#[OA\Post(
    path: '/administration/notes-transmises/{id}/valider-secretariat', operationId: 'validerNotesSecretariat',
    summary: 'Valider une feuille au secretariat academique',
    description: 'ADMIN, SECRETARIAT, SECRETAIRE_ACADEMIQUE. Accepte les statuts transmise et rejetee_direction et passe a validee_secretariat. Une correction en cours bloque la transition.',
    tags: ['Administration des notes'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'message et feuille_notes avec historique.'), new OA\Response(response: 401, description: 'Authentification requise.'), new OA\Response(response: 403, description: 'Role non autorise.'), new OA\Response(response: 404, description: 'Feuille introuvable.'), new OA\Response(response: 422, description: 'Transition impossible ou correction en cours.')]
)]
#[OA\Post(
    path: '/administration/notes-transmises/{id}/rejeter-secretariat', operationId: 'rejeterNotesSecretariat',
    summary: 'Refuser une feuille et la renvoyer a l enseignant',
    description: 'ADMIN, SECRETARIAT, SECRETAIRE_ACADEMIQUE. Accepte transmise, validee_secretariat ou rejetee_direction. Passe a rejetee_secretariat et rouvre la saisie enseignant. Motif obligatoire conserve dans l historique.',
    tags: ['Administration des notes'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['motif'], properties: [new OA\Property(property: 'motif', type: 'string', maxLength: 5000, example: 'Verifier les notes de la copie.')])),
    responses: [new OA\Response(response: 200, description: 'message et feuille_notes avec historique.'), new OA\Response(response: 401, description: 'Authentification requise.'), new OA\Response(response: 403, description: 'Role non autorise.'), new OA\Response(response: 404, description: 'Feuille introuvable.'), new OA\Response(response: 422, description: 'Motif invalide, transition impossible ou correction en cours.')]
)]
#[OA\Post(
    path: '/administration/notes-transmises/{id}/transmettre-direction', operationId: 'transmettreNotesDirection',
    summary: 'Transmettre une feuille validee par le secretariat a la direction',
    description: 'ADMIN, SECRETARIAT, SECRETAIRE_ACADEMIQUE. Seul le statut validee_secretariat permet la transmission. Passe a transmise_direction. Les notes restent verrouillees pour l enseignant.',
    tags: ['Administration des notes'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'message et feuille_notes avec historique.'), new OA\Response(response: 401, description: 'Authentification requise.'), new OA\Response(response: 403, description: 'Role non autorise.'), new OA\Response(response: 404, description: 'Feuille introuvable.'), new OA\Response(response: 422, description: 'Validation du secretariat requise ou correction en cours.')]
)]
#[OA\Post(
    path: '/administration/notes-transmises/{id}/valider-direction', operationId: 'validerNotesDirection',
    summary: 'Valider une feuille par la direction',
    description: 'ADMIN, DIRECTION. Seul le statut transmise_direction est accepte. Passe a validee_direction. Une correction appliquee ensuite renvoie la feuille au statut transmise pour un nouveau controle du secretariat.',
    tags: ['Administration des notes'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'message et feuille_notes avec historique.'), new OA\Response(response: 401, description: 'Authentification requise.'), new OA\Response(response: 403, description: 'Role non autorise.'), new OA\Response(response: 404, description: 'Feuille introuvable.'), new OA\Response(response: 422, description: 'Transmission a la direction requise ou correction en cours.')]
)]
#[OA\Post(
    path: '/administration/notes-transmises/{id}/rejeter-direction', operationId: 'rejeterNotesDirection',
    summary: 'Rejeter une feuille avec motif et la renvoyer au secretariat',
    description: 'ADMIN, DIRECTION. Seul le statut transmise_direction est accepte. Passe a rejetee_direction. Le secretariat doit reexaminer la feuille avant toute nouvelle transmission a la direction.',
    tags: ['Administration des notes'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['motif'], properties: [new OA\Property(property: 'motif', type: 'string', maxLength: 5000, example: 'Incoherence dans les notes transmises.')])),
    responses: [new OA\Response(response: 200, description: 'message et feuille_notes avec historique.'), new OA\Response(response: 401, description: 'Authentification requise.'), new OA\Response(response: 403, description: 'Role non autorise.'), new OA\Response(response: 404, description: 'Feuille introuvable.'), new OA\Response(response: 422, description: 'Motif invalide, transition impossible ou correction en cours.')]
)]
final class NotesTransmisesDocumentation {}
