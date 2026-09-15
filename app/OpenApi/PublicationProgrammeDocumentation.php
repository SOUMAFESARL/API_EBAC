<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'PublicationCalendrier', type: 'object', properties: [
    new OA\Property(property: 'statut', type: 'string', enum: ['non_publie', 'publie']),
    new OA\Property(property: 'version', type: 'integer', example: 1),
    new OA\Property(property: 'date_publication', type: 'string', format: 'date-time', nullable: true),
    new OA\Property(property: 'date_retrait', type: 'string', format: 'date-time', nullable: true),
])]
#[OA\Schema(schema: 'EtatPublicationCalendrier', allOf: [new OA\Schema(ref: '#/components/schemas/PublicationCalendrier')], properties: [
    new OA\Property(property: 'calendrier', ref: '#/components/schemas/CalendrierAcademique'),
    new OA\Property(property: 'visible_utilisateurs', type: 'boolean'),
])]
#[OA\Get(
    path: '/programme', operationId: 'consulterProgrammePublie',
    summary: 'Consulter le calendrier académique publié',
    description: 'Retourne tout le calendrier de l’année : tous les modules, leurs examens et rattrapages, jours fériés, congés et grandes vacances. Un seul statut de publication pour le calendrier entier. Le champ calendrier vaut null avant publication ou après retrait. Accessible au secrétariat académique, gestionnaire, étudiant et enseignant, même sans inscription ni créneau. Le filtre id_module_calendrier est interdit. Sans année explicite, utilise l’année active ou en cours.',
    tags: ['Publication du programme'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'id_annee_academique', in: 'query', schema: new OA\Schema(type: 'integer'))],
    responses: [
        new OA\Response(response: 200, description: 'Calendrier complet publié ou calendrier null.', content: new OA\JsonContent(type: 'object', properties: [
            new OA\Property(property: 'annee_academique', nullable: true, allOf: [new OA\Schema(ref: '#/components/schemas/AnneeAcademique')]),
            new OA\Property(property: 'publication', ref: '#/components/schemas/PublicationCalendrier'),
            new OA\Property(property: 'calendrier', nullable: true, allOf: [new OA\Schema(ref: '#/components/schemas/CalendrierAcademique')]),
            new OA\Property(property: 'message', type: 'string', nullable: true),
        ])),
        new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 403, description: 'Rôle non autorisé.'),
        new OA\Response(response: 422, description: 'Filtres invalides.'),
    ]
)]
#[OA\Get(
    path: '/administration/publication-programme', operationId: 'etatPublicationProgramme',
    summary: 'Consulter l’état de publication du calendrier académique',
    description: 'Le tableau programmes contient une entrée pour le calendrier complet de l’année sélectionnée, ou aucune si aucun calendrier n’existe. Un seul statut et une seule version couvrent tous ses modules. Le filtre id_module_calendrier est interdit.',
    tags: ['Publication du programme'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'id_annee_academique', in: 'query', schema: new OA\Schema(type: 'integer'))],
    responses: [
        new OA\Response(response: 200, description: 'État de publication du calendrier.', content: new OA\JsonContent(type: 'object', properties: [
            new OA\Property(property: 'annee_academique', nullable: true, allOf: [new OA\Schema(ref: '#/components/schemas/AnneeAcademique')]),
            new OA\Property(property: 'programmes', type: 'array', items: new OA\Items(ref: '#/components/schemas/EtatPublicationCalendrier')),
        ])),
        new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 403, description: 'Rôle ADMIN requis.'),
        new OA\Response(response: 422, description: 'Filtres invalides.'),
    ]
)]
#[OA\Post(
    path: '/administration/publication-programme/{calendrier_id}/publier', operationId: 'publierProgramme',
    summary: 'Publier tout le calendrier académique',
    description: 'Publie le calendrier et tous ses modules et événements en une seule action. Aucun corps de requête ni créneau requis ; au moins un module est nécessaire. Un nouvel appel sur un calendrier déjà publié conserve sa version. Les autres calendriers conservent leur statut.',
    tags: ['Publication du programme'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'calendrier_id', in: 'path', required: true, description: 'Champ calendrier.id renvoyé par GET /parametres/annees-academiques/{id}/calendrier.', schema: new OA\Schema(type: 'integer'))],
    responses: [
        new OA\Response(response: 200, description: 'Calendrier académique publié.', content: new OA\JsonContent(type: 'object', properties: [
            new OA\Property(property: 'message', type: 'string'),
            new OA\Property(property: 'programme', ref: '#/components/schemas/EtatPublicationCalendrier'),
        ])),
        new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 403, description: 'Rôle ADMIN requis.'),
        new OA\Response(response: 404, description: 'Calendrier introuvable ou année supprimée.'),
        new OA\Response(response: 422, description: 'Calendrier sans module ou paramètres invalides.'),
    ]
)]
#[OA\Post(
    path: '/administration/publication-programme/{calendrier_id}/retire', operationId: 'retirerProgramme',
    summary: 'Retirer la publication de tout le calendrier académique',
    description: 'Masque immédiatement le calendrier entier et tous ses modules sans supprimer les données.',
    tags: ['Publication du programme'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'calendrier_id', in: 'path', required: true, description: 'Identifiant du calendrier académique.', schema: new OA\Schema(type: 'integer'))],
    responses: [
        new OA\Response(response: 200, description: 'Publication du calendrier retirée.', content: new OA\JsonContent(type: 'object', properties: [
            new OA\Property(property: 'message', type: 'string'),
            new OA\Property(property: 'programme', ref: '#/components/schemas/EtatPublicationCalendrier'),
        ])),
        new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 403, description: 'Rôle ADMIN requis.'),
        new OA\Response(response: 404, description: 'Calendrier introuvable ou année supprimée.'),
        new OA\Response(response: 422, description: 'Calendrier non publié.'),
    ]
)]
final class PublicationProgrammeDocumentation {}
