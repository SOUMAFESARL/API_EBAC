<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Bulletin de notes calcule', description: 'Bulletin par etudiant pour le secretariat et la direction, calcule sur les notes definitivement validees. Accessible aux comptes actifs ADMIN, SECRETARIAT, SECRETAIRE_ACADEMIQUE et DIRECTION. Ne cree ni ne publie de bulletin enregistre.')]
#[OA\Get(path: '/administration/bulletins-notes/{id}', tags: ['Bulletin de notes calcule'], security: [['sanctum' => []]],
    summary: 'Bulletin etudiant : matieres, coefficients, notes, decisions, enseignants et moyenne',
    description: 'id est l identifiant etudiant. Inscription requise dans la promotion et l annee demandees. Seules les feuilles validee_direction contribuent. Meme calcul de notes que /administration/notes-matieres ; moyenne generale ponderee par les coefficients des matieres, sans arrondi intermediaire. Les matieres sans note validee pour cet etudiant sont absentes. Bulletin partiel si toutes les evaluations ne sont pas encore validees. credits_valides est la somme des coefficients des matieres validees. Une correction renvoyee au controle ne contribue de nouveau qu apres revalidation.',
    parameters: [
        new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Identifiant etudiant.', schema: new OA\Schema(type: 'integer'), example: 24),
        new OA\Parameter(name: 'id_promotion', in: 'query', required: true, schema: new OA\Schema(type: 'integer'), example: 22),
        new OA\Parameter(name: 'id_annee_academique', in: 'query', required: true, schema: new OA\Schema(type: 'integer'), example: 16),
    ], responses: [new OA\Response(response: 200, description: 'Bulletin calcule en temps reel.', content: new OA\JsonContent(type: 'object', properties: [
        new OA\Property(property: 'bulletin', type: 'object', properties: [
            new OA\Property(property: 'id_inscription', type: 'integer', example: 1),
            new OA\Property(property: 'etudiant', type: 'object', properties: [
                new OA\Property(property: 'id', type: 'integer', example: 24),
                new OA\Property(property: 'matricule', type: 'string', example: 'EBAC-0016-2026'),
                new OA\Property(property: 'nom', type: 'string', example: 'ANGE'),
                new OA\Property(property: 'prenoms', type: 'string', example: 'HERO'),
                new OA\Property(property: 'photo_identite_url', type: 'string', nullable: true),
            ]),
            new OA\Property(property: 'promotion', type: 'object', nullable: true, properties: [
                new OA\Property(property: 'id', type: 'integer', example: 22),
                new OA\Property(property: 'code', type: 'string', nullable: true, example: 'PROMO-000001'),
                new OA\Property(property: 'num_promotion', type: 'integer', example: 17),
            ]),
            new OA\Property(property: 'niveau', type: 'object', nullable: true, properties: [
                new OA\Property(property: 'id', type: 'integer', example: 25),
                new OA\Property(property: 'code', type: 'string', example: 'A1'),
                new OA\Property(property: 'libelle', type: 'string', example: '1ere Annee'),
            ]),
            new OA\Property(property: 'annee_academique', type: 'object', properties: [
                new OA\Property(property: 'id', type: 'integer', example: 16),
                new OA\Property(property: 'libelle', type: 'string', example: '2026-2027'),
            ]),
            new OA\Property(property: 'matieres', type: 'array', items: new OA\Items(type: 'object', properties: [
                new OA\Property(property: 'numero', type: 'integer', example: 1),
                new OA\Property(property: 'matiere', type: 'object', properties: [
                    new OA\Property(property: 'id', type: 'integer', example: 21),
                    new OA\Property(property: 'code', type: 'string', example: 'MAT-IB-A1'),
                    new OA\Property(property: 'libelle', type: 'string', example: 'Introduction Biblique'),
                ]),
                new OA\Property(property: 'coefficient', type: 'number', example: 2),
                new OA\Property(property: 'note', type: 'number', nullable: true, example: 15),
                new OA\Property(property: 'note_validation', type: 'number', example: 10),
                new OA\Property(property: 'nombre_notes_validees', type: 'integer', example: 1),
                new OA\Property(property: 'decision', type: 'string', enum: ['valide', 'ajourne', 'en_attente'], example: 'valide'),
                new OA\Property(property: 'decision_libelle', type: 'string', example: 'Validé'),
                new OA\Property(property: 'enseignants', type: 'array', items: new OA\Items(type: 'object', properties: [
                    new OA\Property(property: 'id', type: 'integer', example: 44),
                    new OA\Property(property: 'nom', type: 'string', example: 'KOUASSI'),
                    new OA\Property(property: 'prenoms', type: 'string', example: 'Ange'),
                ])),
                new OA\Property(property: 'annee', type: 'string', example: '2026-2027'),
            ])),
            new OA\Property(property: 'moyenne_generale', type: 'number', nullable: true, example: 15),
            new OA\Property(property: 'total_coefficients', type: 'number', example: 2),
            new OA\Property(property: 'nombre_matieres', type: 'integer', example: 1),
            new OA\Property(property: 'matieres_validees', type: 'integer', example: 1),
            new OA\Property(property: 'credits_valides', type: 'number', example: 2),
            new OA\Property(property: 'regle_credits', type: 'string', example: 'somme_coefficients_matieres_validees'),
        ]),
    ])), new OA\Response(response: 401, description: 'Authentification requise.'), new OA\Response(response: 403, description: 'Role interdit ou compte inactif.'), new OA\Response(response: 404, description: 'Etudiant ou inscription introuvable.'), new OA\Response(response: 422, description: 'Promotion ou annee invalide/manquante.')])]
final class BulletinNotesEtudiantDocumentation {}
