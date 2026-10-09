<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'NoteTransmiseAdministration', type: 'object',
    properties: [
        new OA\Property(property: 'evaluation', type: 'string', example: 'devoir_1'),
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'id_etudiant', type: 'integer', example: 12),
        new OA\Property(property: 'note', type: 'number', minimum: 0, maximum: 20, example: 14),
        new OA\Property(property: 'etudiant', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer', example: 12),
            new OA\Property(property: 'matricule', type: 'string', example: 'ETU-001'),
            new OA\Property(property: 'nom', type: 'string', example: 'KONE'),
            new OA\Property(property: 'prenoms', type: 'string', example: 'Jean'),
        ]),
    ]
)]
#[OA\Schema(
    schema: 'FeuilleNotesTransmiseAdministration', type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'id_annee_academique', type: 'integer'),
        new OA\Property(property: 'id_promotion', type: 'integer'),
        new OA\Property(property: 'id_matiere', type: 'integer', nullable: true),
        new OA\Property(property: 'id_seance', type: 'integer', nullable: true, description: 'Seance de la feuille ; null pour les anciennes feuilles.'),
        new OA\Property(property: 'id_cours', type: 'integer', nullable: true),
        new OA\Property(property: 'statut', type: 'string', description: 'Statut selon le role : a_verifier au secretariat ; en_attente, validee ou rejetee a la direction. ADMIN conserve le statut interne.', enum: ['a_verifier', 'en_attente', 'validee', 'rejetee', 'transmise', 'validee_secretariat', 'rejetee_secretariat', 'transmise_direction', 'validee_direction', 'rejetee_direction']),
        new OA\Property(property: 'statut_workflow', type: 'string', enum: ['transmise', 'validee_secretariat', 'rejetee_secretariat', 'transmise_direction', 'validee_direction', 'rejetee_direction'], example: 'transmise'),
        new OA\Property(property: 'motif', type: 'string', nullable: true, description: 'Motif du rejet actuel par le secretariat ou la direction ; null si la feuille ne reste pas rejetee.'),
        new OA\Property(property: 'date_transmission', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'annee_academique', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'libelle', type: 'string', example: '2026-2027'),
        ]),
        new OA\Property(property: 'promotion', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'code', type: 'string', nullable: true),
            new OA\Property(property: 'num_promotion', type: 'integer'),
        ]),
        new OA\Property(property: 'matiere', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'code', type: 'string'),
            new OA\Property(property: 'libelle', type: 'string'),
        ]),
        new OA\Property(property: 'cours', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'code', type: 'string'),
            new OA\Property(property: 'libelle', type: 'string'),
        ]),
        new OA\Property(property: 'enseignant', type: 'object', nullable: true, description: 'Auteur de la transmission, null si inconnu.', properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'nom', type: 'string'),
            new OA\Property(property: 'prenoms', type: 'string'),
        ]),
        new OA\Property(property: 'derniere_modification_par', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'nom', type: 'string'),
            new OA\Property(property: 'prenoms', type: 'string'),
        ]),
        new OA\Property(property: 'historique', type: 'array', nullable: true, description: 'Null dans la liste ; historique avec acteurs dans le detail.', items: new OA\Items(type: 'object')),
        new OA\Property(property: 'notes', type: 'array', items: new OA\Items(ref: '#/components/schemas/NoteTransmiseAdministration')),
    ]
)]
#[OA\Get(
    path: '/administration/notes-transmises', operationId: 'listerNotesTransmisesAdministration',
    summary: 'Consulter les feuilles de notes transmises par les enseignants',
    description: 'Accessible aux roles ADMIN, SECRETARIAT, SECRETAIRE_ACADEMIQUE et DIRECTION. Circuit : transmise → validee_secretariat → transmise_direction → validee_direction. Un refus du secretariat (rejetee_secretariat) rouvre la saisie enseignant et exige une nouvelle transmission. Un rejet du secretariat ou de la direction rouvre la saisie de la seule feuille rejetee pour correction et nouvelle transmission. Les brouillons sont exclus. Ces validations ne publient pas les notes aux etudiants.',
    tags: ['Administration des notes'], security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'id_annee_academique', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_seance', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_promotion', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_matiere', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'statut', in: 'query', schema: new OA\Schema(type: 'string', enum: ['a_verifier', 'en_attente', 'validee', 'rejetee', 'transmise', 'validee_secretariat', 'rejetee_secretariat', 'transmise_direction', 'validee_direction', 'rejetee_direction'])),
        new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100)),
    ],
    responses: [new OA\Response(response: 200, description: 'Liste paginee des feuilles transmises avec le tableau des notes et leur enseignant.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'feuilles_notes', type: 'array', items: new OA\Items(allOf: [
            new OA\Schema(ref: '#/components/schemas/FeuilleNotesTransmiseAdministration'),
            new OA\Schema(type: 'object', properties: [new OA\Property(property: 'nombre_notes', type: 'integer', example: 1)]),
        ])),
        new OA\Property(property: 'meta', type: 'object', properties: [
            new OA\Property(property: 'current_page', type: 'integer', example: 1),
            new OA\Property(property: 'last_page', type: 'integer', example: 1),
            new OA\Property(property: 'per_page', type: 'integer', example: 15),
            new OA\Property(property: 'total', type: 'integer', example: 1),
            new OA\Property(property: 'from', type: 'integer', nullable: true, example: 1),
            new OA\Property(property: 'to', type: 'integer', nullable: true, example: 1),
        ]),
    ])),
        new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 403, description: 'Acces reserve a l administration.'),
        new OA\Response(response: 422, description: 'Filtres invalides.')]
)]
#[OA\Get(
    path: '/administration/notes-transmises/{id}', operationId: 'afficherNotesTransmisesAdministration',
    summary: 'Consulter les notes et les etudiants d une feuille transmise',
    tags: ['Administration des notes'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'feuille_notes avec contexte, notes des etudiants et historique : action, statut_avant, statut_apres, motif, created_at et acteur.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'feuille_notes', ref: '#/components/schemas/FeuilleNotesTransmiseAdministration'),
    ])),
        new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 403, description: 'Acces reserve a l administration.'),
        new OA\Response(response: 404, description: 'Feuille inexistante ou non transmise.')]
)]
#[OA\Post(
    path: '/administration/notes-transmises/{id}/valider-secretariat', operationId: 'validerNotesSecretariat',
    summary: 'Valider une feuille au secretariat academique (ancien circuit)',
    deprecated: true,
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
    summary: 'Transmettre une feuille verifiee par le secretariat a la direction',
    description: 'ADMIN, SECRETARIAT, SECRETAIRE_ACADEMIQUE. Apres verification, transmettre directement depuis transmise ou rejetee_direction. Accepte aussi validee_secretariat pour les anciennes feuilles. Aucune validation intermediaire requise. Passe a transmise_direction. Les notes restent verrouillees pour l enseignant.',
    tags: ['Administration des notes'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'message et feuille_notes avec historique.'), new OA\Response(response: 401, description: 'Authentification requise.'), new OA\Response(response: 403, description: 'Role non autorise.'), new OA\Response(response: 404, description: 'Feuille introuvable.'), new OA\Response(response: 422, description: 'Feuille non transmissible ou correction en cours.')]
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
    description: 'ADMIN, DIRECTION. Seul le statut transmise_direction est accepte. Passe a rejetee_direction. La saisie de cette feuille est rouverte pour l enseignant, qui peut corriger puis retransmettre au secretariat.',
    tags: ['Administration des notes'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['motif'], properties: [new OA\Property(property: 'motif', type: 'string', maxLength: 5000, example: 'Incoherence dans les notes transmises.')])),
    responses: [new OA\Response(response: 200, description: 'message et feuille_notes avec historique.'), new OA\Response(response: 401, description: 'Authentification requise.'), new OA\Response(response: 403, description: 'Role non autorise.'), new OA\Response(response: 404, description: 'Feuille introuvable.'), new OA\Response(response: 422, description: 'Motif invalide, transition impossible ou correction en cours.')]
)]
final class NotesTransmisesDocumentation {}
