<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Tableau des notes par matiere', description: 'Tableaux des notes definitivement validees, pour le secretariat et la direction. Mise a jour automatique a la lecture apres validation direction. Compte actif requis. Accessible a ADMIN, SECRETARIAT, SECRETAIRE_ACADEMIQUE et DIRECTION.')]
#[OA\Get(path: '/administration/notes-matieres', tags: ['Tableau des notes par matiere'], security: [['sanctum' => []]],
    summary: 'Tableaux des etudiants, notes et decisions par matiere',
    description: 'Uniquement les feuilles au statut validee_direction de la promotion et de l annee demandees. Les matieres sans note validee sont absentes. Tous les inscrits de l annee sont affiches ; note null et decision en_attente en absence de note validee. Moyenne arithmetique des notes validees. Le coefficient appartient a la matiere et intervient dans la moyenne generale. Decision sur la moyenne non arrondie selon note_validation de la matiere (10 par defaut). Les corrections renvoyant une feuille au controle ne contribuent de nouveau qu apres revalidation. Pagination des matieres, pas des etudiants.',
    parameters: [
        new OA\Parameter(name: 'id_promotion', in: 'query', required: true, schema: new OA\Schema(type: 'integer'), example: 22),
        new OA\Parameter(name: 'id_annee_academique', in: 'query', required: true, schema: new OA\Schema(type: 'integer'), example: 16),
        new OA\Parameter(name: 'id_matiere', in: 'query', schema: new OA\Schema(type: 'integer'), example: 21),
        new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 20)),
    ], responses: [new OA\Response(response: 200, description: 'Tableaux par matiere.', content: new OA\JsonContent(type: 'object', properties: [
        new OA\Property(property: 'promotion', type: 'object', properties: [
            new OA\Property(property: 'id', type: 'integer', example: 22),
            new OA\Property(property: 'code', type: 'string', nullable: true, example: 'PROMO-000001'),
            new OA\Property(property: 'num_promotion', type: 'integer', example: 17),
        ]),
        new OA\Property(property: 'annee_academique', type: 'object', properties: [
            new OA\Property(property: 'id', type: 'integer', example: 16),
            new OA\Property(property: 'libelle', type: 'string', example: '2026-2027'),
        ]),
        new OA\Property(property: 'matieres', type: 'array', items: new OA\Items(type: 'object', properties: [
            new OA\Property(property: 'id', type: 'integer', example: 21),
            new OA\Property(property: 'code', type: 'string', example: 'MAT-IB-A1'),
            new OA\Property(property: 'libelle', type: 'string', example: 'Introduction Biblique'),
            new OA\Property(property: 'note_validation', type: 'number', example: 10),
            new OA\Property(property: 'effectif', type: 'integer', example: 1),
            new OA\Property(property: 'etudiants', type: 'array', items: new OA\Items(type: 'object', properties: [
                new OA\Property(property: 'numero', type: 'integer', example: 1),
                new OA\Property(property: 'id', type: 'integer', example: 24),
                new OA\Property(property: 'nom', type: 'string', example: 'ANGE'),
                new OA\Property(property: 'prenoms', type: 'string', example: 'HERO'),
                new OA\Property(property: 'matricule', type: 'string', example: 'EBAC-0016-2026'),
                new OA\Property(property: 'note', type: 'number', nullable: true, example: 15),
                new OA\Property(property: 'nombre_notes_validees', type: 'integer', example: 1),
                new OA\Property(property: 'decision', type: 'string', enum: ['valide', 'ajourne', 'en_attente'], example: 'valide'),
                new OA\Property(property: 'decision_libelle', type: 'string', example: 'Validé'),
            ])),
        ])),
        new OA\Property(property: 'meta', type: 'object', properties: [
            new OA\Property(property: 'total', type: 'integer', example: 1),
            new OA\Property(property: 'current_page', type: 'integer', example: 1),
            new OA\Property(property: 'last_page', type: 'integer', example: 1),
            new OA\Property(property: 'per_page', type: 'integer', example: 20),
            new OA\Property(property: 'from', type: 'integer', nullable: true, example: 1),
            new OA\Property(property: 'to', type: 'integer', nullable: true, example: 1),
        ]),
    ])), new OA\Response(response: 401, description: 'Authentification requise.'), new OA\Response(response: 403, description: 'Role interdit ou compte inactif.'), new OA\Response(response: 422, description: 'Promotion, annee, matiere ou pagination invalide.')])]
final class TableauNotesMatieresDocumentation {}
