<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'CorrectionGroupeeLigne', type: 'object', properties: [
    new OA\Property(property: 'id', type: 'integer', example: 12),
    new OA\Property(property: 'id_note', type: 'integer', example: 101),
    new OA\Property(property: 'note_initiale', type: 'number', example: 12),
    new OA\Property(property: 'note_proposee', type: 'number', example: 16),
    new OA\Property(property: 'note_actuelle', type: 'number', example: 12),
    new OA\Property(property: 'note_finale', type: 'number', nullable: true, example: null),
    new OA\Property(property: 'motif', type: 'string', example: 'Erreur de report'),
    new OA\Property(property: 'statut', type: 'string', example: 'en_attente'),
    new OA\Property(property: 'statut_libelle', type: 'string', example: 'À vérifier'),
    new OA\Property(property: 'demandeur', type: 'object', properties: [
        new OA\Property(property: 'id', type: 'integer', example: 5),
        new OA\Property(property: 'nom', type: 'string', example: 'KOUASSI'),
        new OA\Property(property: 'prenoms', type: 'string', example: 'Ange'),
    ]),
    new OA\Property(property: 'actions', type: 'object', properties: [
        new OA\Property(property: 'peut_appliquer', type: 'boolean', example: true),
        new OA\Property(property: 'peut_rejeter', type: 'boolean', example: true),
    ]),
])]
#[OA\Schema(schema: 'FeuilleCorrectionsGroupees', type: 'object', properties: [
    new OA\Property(property: 'id_feuille_notes', type: 'integer', example: 1),
    new OA\Property(property: 'id_seance', type: 'integer', nullable: true, example: 8),
    new OA\Property(property: 'id_annee_academique', type: 'integer', example: 1),
    new OA\Property(property: 'evaluation', type: 'string', nullable: true, example: 'Participation'),
    new OA\Property(property: 'matiere', type: 'object', nullable: true, properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'code', type: 'string', example: 'DFC'),
        new OA\Property(property: 'libelle', type: 'string', example: 'Doctrine de la Foi Chrétienne'),
    ]),
    new OA\Property(property: 'cours', type: 'object', nullable: true),
    new OA\Property(property: 'promotion', type: 'object', nullable: true, properties: [
        new OA\Property(property: 'id', type: 'integer', example: 17),
        new OA\Property(property: 'num_promotion', type: 'integer', example: 17),
    ]),
    new OA\Property(property: 'enseignant', type: 'object', nullable: true, description: 'Demandeur unique ; null si plusieurs demandeurs.', properties: [
        new OA\Property(property: 'id', type: 'integer', example: 5),
        new OA\Property(property: 'nom', type: 'string', example: 'KOUASSI'),
        new OA\Property(property: 'prenoms', type: 'string', example: 'Ange'),
    ]),
    new OA\Property(property: 'enseignants', type: 'array', items: new OA\Items(type: 'object')),
    new OA\Property(property: 'date_demande', type: 'string', format: 'date-time'),
    new OA\Property(property: 'statut', type: 'string', enum: ['en_attente', 'appliquee', 'rejetee', 'mixte'], example: 'en_attente'),
    new OA\Property(property: 'statut_libelle', type: 'string', example: 'À vérifier'),
    new OA\Property(property: 'nombre_corrections', type: 'integer', example: 2),
    new OA\Property(property: 'corrections_en_attente', type: 'integer', example: 2),
    new OA\Property(property: 'ids_corrections_applicables', type: 'array', items: new OA\Items(type: 'integer'), example: [12, 13]),
    new OA\Property(property: 'ids_corrections_rejetables', type: 'array', items: new OA\Items(type: 'integer'), example: [12, 13]),
    new OA\Property(property: 'notes_saisies', type: 'integer', example: 3),
    new OA\Property(property: 'effectif', type: 'integer', example: 3),
    new OA\Property(property: 'moyenne_cours', type: 'number', nullable: true, example: 13.67),
    new OA\Property(property: 'notes', type: 'array', description: 'Toutes les notes de cette evaluation, y compris les etudiants sans correction. Chaque note contient ses demandes de correction et leur historique de statuts.', items: new OA\Items(type: 'object', properties: [
        new OA\Property(property: 'id', type: 'integer', example: 101),
        new OA\Property(property: 'id_etudiant', type: 'integer', example: 1),
        new OA\Property(property: 'evaluation', type: 'string', nullable: true, example: 'Participation'),
        new OA\Property(property: 'note', type: 'number', example: 12),
        new OA\Property(property: 'etudiant', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer', example: 1),
            new OA\Property(property: 'matricule', type: 'string', example: 'EBAC-0016-2026'),
            new OA\Property(property: 'nom', type: 'string', example: 'ANGE'),
            new OA\Property(property: 'prenoms', type: 'string', example: 'HERO'),
        ]),
        new OA\Property(property: 'corrections', type: 'array', items: new OA\Items(ref: '#/components/schemas/CorrectionGroupeeLigne')),
    ])),
    new OA\Property(property: 'actions', type: 'object', properties: [
        new OA\Property(property: 'peut_appliquer', type: 'boolean', example: true),
        new OA\Property(property: 'peut_rejeter', type: 'boolean', example: true),
    ]),
    new OA\Property(property: 'historique', type: 'array', items: new OA\Items(type: 'object', properties: [
        new OA\Property(property: 'id_correction', type: 'integer'),
        new OA\Property(property: 'id_acteur', type: 'integer'),
        new OA\Property(property: 'action', type: 'string'),
        new OA\Property(property: 'details', type: 'string', nullable: true),
        new OA\Property(property: 'created_at', type: 'string'),
    ])),
])]
#[OA\Schema(schema: 'SelectionCorrectionsGroupees', type: 'object', required: ['evaluation', 'ids_corrections'], properties: [
    new OA\Property(property: 'evaluation', type: 'string', nullable: true, example: 'Participation'),
    new OA\Property(property: 'ids_corrections', type: 'array', minItems: 1, maxItems: 100, uniqueItems: true, items: new OA\Items(type: 'integer'), example: [12, 13]),
])]
#[OA\Get(path: '/administration/corrections-notes/groupes', tags: ['Corrections des notes'], security: [['sanctum' => []]],
    summary: 'Tableau secretariat : demandes regroupees par feuille et evaluation',
    description: 'Une ligne par feuille et evaluation ayant des demandes. Inclut les notes actuelles, corrections, enseignant, promotion, compteurs et actions. Une demande active rend le groupe en_attente ; sinon appliquee, rejetee ou mixte. Les demandes historiques restent visibles. Pas de direction obligatoire.',
    parameters: [
        new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string', maxLength: 255), description: 'Matiere, cours, promotion, enseignant ou evaluation.'),
        new OA\Parameter(name: 'statut', in: 'query', schema: new OA\Schema(type: 'string', enum: ['en_attente', 'appliquee', 'rejetee', 'mixte'])),
        new OA\Parameter(name: 'id_promotion', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_annee_academique', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 20)),
    ], responses: [new OA\Response(response: 200, description: 'Liste des groupes et pagination.', content: new OA\JsonContent(type: 'object', properties: [
        new OA\Property(property: 'feuilles_corrections', type: 'array', items: new OA\Items(ref: '#/components/schemas/FeuilleCorrectionsGroupees')),
        new OA\Property(property: 'meta', type: 'object', properties: [
            new OA\Property(property: 'total', type: 'integer'), new OA\Property(property: 'current_page', type: 'integer'),
            new OA\Property(property: 'last_page', type: 'integer'), new OA\Property(property: 'per_page', type: 'integer'),
            new OA\Property(property: 'from', type: 'integer', nullable: true), new OA\Property(property: 'to', type: 'integer', nullable: true),
        ]),
    ])), new OA\Response(response: 401, description: 'Authentification requise.'), new OA\Response(response: 403, description: 'Acces interdit.'), new OA\Response(response: 422, description: 'Filtres invalides.')])]
#[OA\Get(path: '/administration/corrections-notes/groupes/{id}', tags: ['Corrections des notes'], security: [['sanctum' => []]],
    summary: 'Panneau secretariat : tous les etudiants et corrections de cette evaluation',
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, description: 'id_feuille_notes retourne par la liste.', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'evaluation', in: 'query', required: true, allowEmptyValue: true, description: 'Valeur exacte de la liste. Pour null, envoyer evaluation=.', schema: new OA\Schema(type: 'string', nullable: true), example: 'Participation')],
    responses: [new OA\Response(response: 200, description: 'Detail groupe.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'feuille_corrections', ref: '#/components/schemas/FeuilleCorrectionsGroupees')])), new OA\Response(response: 404, description: 'Groupe introuvable.'), new OA\Response(response: 422, description: 'Evaluation manquante ou invalide.')])]
#[OA\Post(path: '/administration/corrections-notes/groupes/{id}/appliquer', tags: ['Corrections des notes'], security: [['sanctum' => []]],
    summary: 'Appliquer les corrections selectionnees de la feuille (secretariat, ADMIN)',
    description: 'Envoyer evaluation et ids_corrections_applicables du GET dans ids_corrections. Maximum 100. Toutes les corrections doivent appartenir au groupe et etre applicables. Transaction atomique : une erreur annule tout, y compris traces et notes. Pas de decision de direction requise. Les corrections nouvellement arrivees ne sont pas incluses implicitement.',
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/SelectionCorrectionsGroupees')),
    responses: [new OA\Response(response: 200, description: 'Notes mises a jour et groupe actualise.', content: new OA\JsonContent(type: 'object', properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Corrections appliquées.'),
        new OA\Property(property: 'feuille_corrections', ref: '#/components/schemas/FeuilleCorrectionsGroupees'),
    ])), new OA\Response(response: 403, description: 'Seuls ADMIN et le secretariat peuvent appliquer.'), new OA\Response(response: 422, description: 'Selection, etat ou note initiale invalide.')])]
#[OA\Post(path: '/administration/corrections-notes/groupes/{id}/rejeter', tags: ['Corrections des notes'], security: [['sanctum' => []]],
    summary: 'Rejeter les corrections selectionnees avec motif (secretariat, ADMIN)',
    description: 'Envoyer evaluation, ids_corrections_rejetables du GET dans ids_corrections et motif. Rejet atomique, sans changer les notes.',
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(allOf: [new OA\Schema(ref: '#/components/schemas/SelectionCorrectionsGroupees'), new OA\Schema(type: 'object', required: ['motif'], properties: [new OA\Property(property: 'motif', type: 'string', minLength: 1, maxLength: 5000, example: 'Copie non justifiée')])])),
    responses: [new OA\Response(response: 200, description: 'Rejets traces et groupe actualise.', content: new OA\JsonContent(type: 'object', properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Corrections rejetées.'),
        new OA\Property(property: 'feuille_corrections', ref: '#/components/schemas/FeuilleCorrectionsGroupees'),
    ])), new OA\Response(response: 403, description: 'Seuls ADMIN et le secretariat peuvent rejeter.'), new OA\Response(response: 422, description: 'Motif, selection ou etat invalide.')])]
final class CorrectionsGroupeesDocumentation {}
