<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/enseignant/transmissions-notes/tableau', operationId: 'tableauToutesMesNotesTransmises',
    summary: 'Toutes mes notes transmises dans un tableau',
    description: 'Tableau directement a la racine. Un groupe par feuille de seance avec notes[] (id, id_etudiant, evaluation, note, etudiant). Le contexte est present une seule fois : id_feuille_notes, id_seance, id_cours, id_matiere, id_promotion, id_annee_academique, statut (toujours transmise), statut_workflow, date_transmission, cours, matiere et promotion. Sans filtre : toutes les notes transmises par l enseignant connecte. Filtres combinables. Pas de pagination. Les brouillons et les feuilles des autres enseignants sont exclus. [] si aucune feuille transmise.',
    tags: ['Notes enseignant'], security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'id_seance', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_cours', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_matiere', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_promotion', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_annee_academique', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'statut', in: 'query', description: 'transmise inclut toutes les etapes du circuit. Les autres valeurs filtrent le statut interne.', schema: new OA\Schema(type: 'string', enum: ['transmise', 'validee_secretariat', 'rejetee_secretariat', 'transmise_direction', 'validee_direction', 'rejetee_direction'])),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Tableau des notes transmises.', content: new OA\JsonContent(type: 'array', items: new OA\Items(allOf: [
            new OA\Schema(type: 'object', properties: [
                new OA\Property(property: 'notes', type: 'array', items: new OA\Items(ref: '#/components/schemas/NoteTransmiseAdministration')),
                new OA\Property(property: 'id_feuille_notes', type: 'integer'),
                new OA\Property(property: 'id_seance', type: 'integer', nullable: true),
                new OA\Property(property: 'id_cours', type: 'integer', nullable: true),
                new OA\Property(property: 'id_matiere', type: 'integer', nullable: true),
                new OA\Property(property: 'id_promotion', type: 'integer'),
                new OA\Property(property: 'id_annee_academique', type: 'integer'),
                new OA\Property(property: 'statut', type: 'string', enum: ['transmise']),
                new OA\Property(property: 'statut_workflow', type: 'string'),
                new OA\Property(property: 'date_transmission', type: 'string', format: 'date-time', nullable: true),
                new OA\Property(property: 'cours', type: 'object', nullable: true),
                new OA\Property(property: 'matiere', type: 'object', nullable: true),
                new OA\Property(property: 'promotion', type: 'object', nullable: true),
            ]),
        ]))),
        new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 403, description: 'Enseignant actif requis.'),
        new OA\Response(response: 422, description: 'Filtres invalides.'),
    ]
)]
#[OA\Get(
    path: '/enseignant/transmissions-notes/feuilles', operationId: 'toutesMesFeuillesNotesTransmises',
    summary: 'Toutes mes feuilles avec leurs notes transmises',
    description: 'Sans pagination. feuilles_notes regroupe les notes par feuille avec contexte, circuit de validation et historique. Acces selon transmise_par : uniquement les feuilles transmises par l enseignant connecte, meme apres une decision administrative ou fin d affectation. Filtres combinables par seance, cours, matiere, promotion, annee et statut.',
    tags: ['Notes enseignant'], security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'id_seance', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_cours', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_matiere', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_promotion', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_annee_academique', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'statut', in: 'query', description: 'transmise inclut toutes les etapes du circuit. Les autres valeurs filtrent le statut interne.', schema: new OA\Schema(type: 'string', enum: ['transmise', 'validee_secretariat', 'rejetee_secretariat', 'transmise_direction', 'validee_direction', 'rejetee_direction'])),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Feuilles et notes transmises.', content: new OA\JsonContent(properties: [
            new OA\Property(property: 'feuilles_notes', type: 'array', items: new OA\Items(allOf: [
                new OA\Schema(ref: '#/components/schemas/SuiviTransmissionNotes'),
                new OA\Schema(type: 'object', properties: [new OA\Property(property: 'notes', type: 'array', items: new OA\Items(ref: '#/components/schemas/NoteTransmiseAdministration'))]),
            ])),
        ])),
        new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 403, description: 'Enseignant actif requis.'),
        new OA\Response(response: 422, description: 'Filtres invalides.'),
    ]
)]
final class NotesTransmisesEnseignantDocumentation {}
