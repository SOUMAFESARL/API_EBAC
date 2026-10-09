<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'SuiviTransmissionNotes', type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', description: 'Identifiant de la feuille de notes.'),
        new OA\Property(property: 'statut', type: 'string', description: 'Reste transmise chez l enseignant ; statut_workflow indique les decisions administratives.', enum: ['transmise']),
        new OA\Property(property: 'statut_workflow', type: 'string', enum: ['transmise', 'validee_secretariat', 'rejetee_secretariat', 'transmise_direction', 'validee_direction', 'rejetee_direction']),
        new OA\Property(property: 'motif', type: 'string', nullable: true, description: 'Motif du rejet actuel par le secretariat ou la direction ; null si la feuille ne reste pas rejetee.'),
        new OA\Property(property: 'date_transmission', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'transmise_par', type: 'integer', description: 'Enseignant ayant transmis la feuille.'),
        new OA\Property(property: 'id_annee_academique', type: 'integer'),
        new OA\Property(property: 'id_promotion', type: 'integer'),
        new OA\Property(property: 'id_matiere', type: 'integer', nullable: true),
        new OA\Property(property: 'id_seance', type: 'integer', nullable: true, description: 'Seance de la feuille ; null pour les anciennes feuilles.'),
        new OA\Property(property: 'id_cours', type: 'integer', nullable: true),
        new OA\Property(property: 'annee_academique', type: 'object', nullable: true),
        new OA\Property(property: 'promotion', type: 'object', nullable: true),
        new OA\Property(property: 'matiere', type: 'object', nullable: true),
        new OA\Property(property: 'cours', type: 'object', nullable: true),
        new OA\Property(property: 'nombre_notes', type: 'integer'),
        new OA\Property(property: 'circuit_validation', type: 'object', properties: [
            new OA\Property(property: 'etape_actuelle', type: 'integer', minimum: 1, maximum: 4, example: 2),
            new OA\Property(property: 'libelle', type: 'string', example: 'Contrôle secrétariat'),
            new OA\Property(property: 'message', type: 'string', example: 'Notes transmises au secrétariat, en cours de contrôle avant soumission à la direction.'),
            new OA\Property(property: 'rejetee', type: 'boolean', example: false),
            new OA\Property(property: 'motif_rejet', type: 'string', nullable: true),
            new OA\Property(property: 'correction_enseignant_requise', type: 'boolean', description: 'Un refus du secrétariat permet une correction puis une nouvelle transmission. Ce champ ne remplace pas les conditions de saisie de l API de notes.'),
            new OA\Property(property: 'validee_et_verrouillee', type: 'boolean', example: false),
            new OA\Property(property: 'etapes', type: 'array', items: new OA\Items(type: 'object', properties: [
                new OA\Property(property: 'numero', type: 'integer'),
                new OA\Property(property: 'libelle', type: 'string'),
                new OA\Property(property: 'statut', type: 'string', enum: ['terminee', 'en_cours', 'en_attente', 'rejetee']),
            ])),
        ]),
        new OA\Property(property: 'derniere_decision', type: 'object', nullable: true, description: 'Dernier événement de l historique (décision ou transmission), avec son acteur.'),
        new OA\Property(property: 'historique', type: 'array', items: new OA\Items(type: 'object', properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'id_feuille_notes', type: 'integer'),
            new OA\Property(property: 'id_acteur', type: 'integer', nullable: true),
            new OA\Property(property: 'action', type: 'string'),
            new OA\Property(property: 'statut_avant', type: 'string'),
            new OA\Property(property: 'statut_apres', type: 'string'),
            new OA\Property(property: 'motif', type: 'string', nullable: true),
            new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
            new OA\Property(property: 'acteur', type: 'object', nullable: true),
        ])),
    ]
)]
#[OA\Get(
    path: '/enseignant/transmissions-notes', operationId: 'listerMesTransmissionsNotes',
    summary: 'Suivre les transmissions de notes de l enseignant connecté',
    description: 'ENSEIGNANT actif. Liste paginée de ses feuilles transmises par matière ou cours, avec le circuit en quatre étapes et l historique. Les brouillons et les transmissions des autres enseignants sont exclus. L accès dépend de transmise_par et reste disponible après les décisions administratives ou la fin d une affectation. Aucun identifiant enseignant à fournir. Les anciennes feuilles sans auteur identifiable ne sont pas attribuées arbitrairement.',
    tags: ['Notes enseignant'], security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'id_annee_academique', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_seance', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_promotion', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_matiere', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_cours', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'statut', in: 'query', description: 'transmise inclut toutes les feuilles transmises, quelle que soit leur etape ; les statuts internes filtrent une etape precise.', schema: new OA\Schema(type: 'string', enum: ['transmise', 'validee_secretariat', 'rejetee_secretariat', 'transmise_direction', 'validee_direction', 'rejetee_direction'])),
        new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 15)),
    ],
    responses: [
        new OA\Response(response: 200, description: 'transmissions et meta de pagination.', content: new OA\JsonContent(properties: [
            new OA\Property(property: 'transmissions', type: 'array', items: new OA\Items(ref: '#/components/schemas/SuiviTransmissionNotes')),
            new OA\Property(property: 'meta', type: 'object'),
        ])),
        new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 403, description: 'Compte enseignant actif requis.'),
        new OA\Response(response: 422, description: 'Filtres invalides.'),
    ]
)]
#[OA\Get(
    path: '/enseignant/transmissions-notes/{id}', operationId: 'afficherMaTransmissionNotes',
    summary: 'Consulter le circuit de validation d une feuille transmise',
    description: 'Utiliser l id retourné par transmissions[].id. Fournit l étape actuelle, les étapes terminées ou en attente, les motifs de rejet et l historique chronologique avec les acteurs. Un refus du secrétariat renvoie à la saisie enseignant ; un rejet de la direction renvoie au contrôle secrétariat. La validation finale termine les quatre étapes. Aucune publication des notes aux étudiants.',
    tags: ['Notes enseignant'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [
        new OA\Response(response: 200, description: 'Détail du suivi.', content: new OA\JsonContent(properties: [new OA\Property(property: 'transmission', ref: '#/components/schemas/SuiviTransmissionNotes')])),
        new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 403, description: 'Compte enseignant actif requis.'),
        new OA\Response(response: 404, description: 'Feuille inexistante, brouillon ou transmise par un autre enseignant.'),
    ]
)]
final class TransmissionsNotesEnseignantDocumentation {}
