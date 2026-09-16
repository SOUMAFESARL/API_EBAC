<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'EtudiantPresence',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'matricule', type: 'string', example: 'ETU-2026-001'),
        new OA\Property(property: 'nom', type: 'string', example: 'KOUAME'),
        new OA\Property(property: 'prenoms', type: 'string', example: 'Jean'),
        new OA\Property(property: 'statut_presence', type: 'string', enum: ['present', 'absent'], nullable: true, example: 'present'),
    ]
)]
#[OA\Schema(
    schema: 'FeuillePresenceDetail',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 10),
        new OA\Property(property: 'date_prevue', type: 'string', format: 'date', example: '2026-09-14'),
        new OA\Property(property: 'heure_debut_prevue', type: 'string', example: '08:00:00'),
        new OA\Property(property: 'heure_fin_prevue', type: 'string', nullable: true, example: '10:00:00'),
        new OA\Property(property: 'statut', type: 'string', example: 'realisee'),
        new OA\Property(property: 'matiere', type: 'object', nullable: true),
        new OA\Property(property: 'module', type: 'object', nullable: true),
        new OA\Property(property: 'cours', type: 'object', nullable: true),
        new OA\Property(property: 'promotion', type: 'object', nullable: true),
        new OA\Property(
            property: 'presence',
            type: 'object',
            properties: [
                new OA\Property(property: 'statut', type: 'string', example: 'a_soumettre'),
                new OA\Property(property: 'presents', type: 'integer', example: 15),
                new OA\Property(property: 'absents', type: 'integer', example: 2),
            ]
        ),
        new OA\Property(
            property: 'etudiants',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/EtudiantPresence')
        ),
        new OA\Property(property: 'modifiable', type: 'boolean', example: true),
        new OA\Property(property: 'date_validation', type: 'string', format: 'date-time', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'PresenceItemPayload',
    type: 'object',
    required: ['id_etudiant', 'statut'],
    properties: [
        new OA\Property(property: 'id_etudiant', type: 'integer', example: 1),
        new OA\Property(property: 'statut', type: 'string', enum: ['present', 'absent'], example: 'present'),
    ]
)]
#[OA\Schema(
    schema: 'PresencePayload',
    type: 'object',
    required: ['presences'],
    properties: [
        new OA\Property(
            property: 'presences',
            type: 'array',
            minItems: 1,
            items: new OA\Items(ref: '#/components/schemas/PresenceItemPayload')
        ),
    ]
)]
#[OA\Get(
    path: '/enseignant/liste-presence',
    operationId: 'listerSeancesPresence',
    summary: 'Lister les séances accessibles pour l’appel',
    description: 'Retourne les séances de l’enseignant connecté avec pour chacune les étudiants concernés et leur statut de présence.',
    tags: ['Liste de présence'],
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'id_matiere', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_cours', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_promotion', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'date_debut', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
        new OA\Parameter(name: 'date_fin', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Séances de l’enseignant avec étudiants et état de leur feuille.',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'seances', type: 'array', items: new OA\Items(ref: '#/components/schemas/FeuillePresenceDetail')),
                    new OA\Property(property: 'nombre_seances', type: 'integer', example: 1),
                ]
            )
        ),
        new OA\Response(response: 401, description: 'Non authentifié.'),
        new OA\Response(response: 403, description: 'Rôle enseignant requis.'),
    ]
)]
#[OA\Get(
    path: '/enseignant/liste-presence/{seance}',
    operationId: 'afficherListePresence',
    summary: 'Afficher les étudiants concernés par une séance pour l’appel',
    description: 'Les étudiants sont calculés côté serveur depuis l’année académique, le niveau et la promotion de la séance.',
    tags: ['Liste de présence'],
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'seance', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Feuille et étudiants concernés avec leur statut de présence.',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'feuille_presence', ref: '#/components/schemas/FeuillePresenceDetail'),
                ]
            )
        ),
        new OA\Response(response: 404, description: 'Séance absente ou appartenant à un autre enseignant.'),
    ]
)]
#[OA\Put(
    path: '/enseignant/liste-presence/{seance}',
    operationId: 'enregistrerListePresence',
    summary: 'Enregistrer la liste de présence en brouillon',
    description: 'Enregistre les statuts (présent ou absent) pour tous les étudiants concernés. La liste doit contenir exactement tous les étudiants sans doublon.',
    tags: ['Liste de présence'],
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'seance', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/PresencePayload')),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Brouillon enregistré avec succès.',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'Liste de présence enregistrée.'),
                    new OA\Property(property: 'feuille_presence', ref: '#/components/schemas/FeuillePresenceDetail'),
                ]
            )
        ),
        new OA\Response(response: 404, description: 'Séance inaccessible.'),
        new OA\Response(response: 422, description: 'Liste incomplète, doublon ou étudiant non concerné.'),
    ]
)]
#[OA\Post(
    path: '/enseignant/liste-presence/{seance}/valider',
    operationId: 'validerListePresence',
    summary: 'Valider définitivement la liste de présence',
    description: 'Opération irréversible. La séance doit être réalisée. Chaque absent reçoit automatiquement un cours à faire.',
    tags: ['Liste de présence'],
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'seance', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(ref: '#/components/schemas/PresencePayload')),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Liste validée définitivement.',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'Liste de présence validée définitivement.'),
                    new OA\Property(property: 'feuille_presence', ref: '#/components/schemas/FeuillePresenceDetail'),
                ]
            )
        ),
        new OA\Response(response: 404, description: 'Séance inaccessible.'),
        new OA\Response(response: 422, description: 'Séance non réalisée, feuille absente/incomplète ou déjà validée.'),
    ]
)]
final class ListePresenceDocumentation {}

