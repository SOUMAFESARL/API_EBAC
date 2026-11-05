<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Bulletin de notes calcule', description: 'Bulletin par etudiant pour le secretariat et la direction, calcule sur les notes definitivement validees. Compte actif ; un de ces droits requis : notes.supervision-notes.consulter ou notes.validation-notes.consulter ou notes.corrections.consulter. ADMIN passe toujours. Ne cree ni ne publie de bulletin enregistre.')]
#[OA\Get(path: '/administration/bulletins-notes/{id}', tags: ['Bulletin de notes calcule'], security: [['sanctum' => []]],
    summary: 'Bulletin etudiant : matieres regroupees par domaine d enseignement',
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
                    new OA\Property(property: 'unite_ensigne_id', description: 'Identifiant du domaine d enseignement de la matiere.', type: 'integer', nullable: true),
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
            new OA\Property(property: 'unites_enseignement', description: 'Chaque unite_ensigne_id apparait une seule fois avec toutes ses matieres dans le tableau matieres. L identifiant de chaque matiere est matiere.id. Le groupe unite_ensigne_id null rassemble les matieres sans domaine. Tableau vide si aucune matiere. La liste bulletin.matieres reste disponible.', type: 'array', example: BulletinNotesEtudiantDocumentation::EXEMPLE_DOMAINES, items: new OA\Items(type: 'object', properties: [
                new OA\Property(property: 'unite_ensigne_id', type: 'integer', nullable: true, example: 2),
                new OA\Property(property: 'matieres', type: 'array', items: new OA\Items(ref: '#/paths/~1administration~1bulletins-notes~1{id}/get/responses/200/content/application~1json/schema/properties/bulletin/properties/matieres/items')),
            ])),
            new OA\Property(property: 'total_coefficients', type: 'number', example: 2),
            new OA\Property(property: 'nombre_matieres', type: 'integer', example: 1),
            new OA\Property(property: 'matieres_validees', type: 'integer', example: 1),
            new OA\Property(property: 'credits_valides', type: 'number', example: 2),
            new OA\Property(property: 'regle_credits', type: 'string', example: 'somme_coefficients_matieres_validees'),
        ]),
    ])), new OA\Response(response: 401, description: 'Authentification requise.'), new OA\Response(response: 403, description: 'Role interdit ou compte inactif.'), new OA\Response(response: 404, description: 'Etudiant ou inscription introuvable.'), new OA\Response(response: 422, description: 'Promotion ou annee invalide/manquante.')])]
final class BulletinNotesEtudiantDocumentation
{
    public const EXEMPLE_DOMAINES = [
        [
            'unite_ensigne_id' => 2,
            'matieres' => [
                [
                    'numero' => 1,
                    'matiere' => ['id' => 8, 'code' => 'BIB-01', 'libelle' => 'Introduction biblique', 'unite_ensigne_id' => 2],
                    'coefficient' => 2, 'note' => 14, 'note_validation' => 10, 'nombre_notes_validees' => 2,
                    'decision' => 'valide', 'decision_libelle' => 'Validé',
                    'enseignants' => [['id' => 5, 'nom' => 'YAO', 'prenoms' => 'Paul']], 'annee' => '2026-2027',
                ],
                [
                    'numero' => 2,
                    'matiere' => ['id' => 9, 'code' => 'BIB-02', 'libelle' => 'Nouveau Testament', 'unite_ensigne_id' => 2],
                    'coefficient' => 3, 'note' => 16, 'note_validation' => 10, 'nombre_notes_validees' => 2,
                    'decision' => 'valide', 'decision_libelle' => 'Validé',
                    'enseignants' => [['id' => 5, 'nom' => 'YAO', 'prenoms' => 'Paul']], 'annee' => '2026-2027',
                ],
            ],
        ],
    ];
}
