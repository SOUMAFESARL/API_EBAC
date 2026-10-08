<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/enseignant/transmissions-notes/tableau-general',
    operationId: 'tableauGeneralNotesTransmisesEnseignant',
    summary: 'Tableau general des notes transmises par matiere',
    description: 'Tableaux par matiere, promotion et annee academique. Une ligne par etudiant ayant une note transmise, une colonne par feuille et evaluation. Les nouvelles transmissions apparaissent automatiquement au prochain appel. Notes manquantes : null. Moyenne arithmetique provisoire des notes presentes, arrondie a deux decimales ; zero est inclus. Seules les feuilles transmises par l enseignant connecte sont incluses, y compris apres validation ou rejet. Les brouillons sont exclus. Les cles de colonnes sont stables et correspondent aux cles de lignes[].notes. Sans resultat : tableaux=[]. Sans pagination.',
    tags: ['Notes enseignant'], security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'id_matiere', in: 'query', schema: new OA\Schema(type: 'integer'), example: 21),
        new OA\Parameter(name: 'id_promotion', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_annee_academique', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_seance', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_cours', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'statut', in: 'query', schema: new OA\Schema(type: 'string', enum: ['transmise', 'validee_secretariat', 'rejetee_secretariat', 'transmise_direction', 'validee_direction', 'rejetee_direction'])),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Tableaux des notes transmises.', content: new OA\JsonContent(properties: [
            new OA\Property(property: 'tableaux', type: 'array', items: new OA\Items(properties: [
                new OA\Property(property: 'id_matiere', type: 'integer', nullable: true),
                new OA\Property(property: 'matiere', type: 'object', nullable: true),
                new OA\Property(property: 'id_promotion', type: 'integer'),
                new OA\Property(property: 'promotion', type: 'object'),
                new OA\Property(property: 'id_annee_academique', type: 'integer'),
                new OA\Property(property: 'annee_academique', type: 'object'),
                new OA\Property(property: 'colonnes', type: 'array', items: new OA\Items(properties: [
                    new OA\Property(property: 'cle', type: 'string'),
                    new OA\Property(property: 'libelle', type: 'string'),
                    new OA\Property(property: 'evaluation', type: 'string'),
                    new OA\Property(property: 'id_feuille_notes', type: 'integer'),
                    new OA\Property(property: 'id_seance', type: 'integer', nullable: true),
                    new OA\Property(property: 'id_cours', type: 'integer', nullable: true),
                    new OA\Property(property: 'date_seance', type: 'string', format: 'date', nullable: true),
                ])),
                new OA\Property(property: 'lignes', type: 'array', items: new OA\Items(properties: [
                    new OA\Property(property: 'id_etudiant', type: 'integer'),
                    new OA\Property(property: 'etudiant', type: 'object', nullable: true),
                    new OA\Property(property: 'notes', type: 'object', additionalProperties: new OA\AdditionalProperties(type: 'number', nullable: true)),
                    new OA\Property(property: 'moyenne', type: 'number', nullable: true),
                ])),
            ])),
        ])),
        new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 403, description: 'Enseignant actif requis.'),
        new OA\Response(response: 422, description: 'Filtres invalides.'),
    ]
)]
final class TableauGeneralNotesEnseignantDocumentation {}
