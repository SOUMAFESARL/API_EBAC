<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'NotesMatierePayload', type: 'object', required: ['id_matiere', 'id_promotion', 'id_annee_academique', 'id_seance'], properties: [
    new OA\Property(property: 'id_seance', type: 'integer', example: 157, description: 'Seance de cet enseignant, de cette matiere, promotion et annee. Seules ses presences sont controlees.'),
    new OA\Property(property: 'id_matiere', type: 'integer', example: 19),
    new OA\Property(property: 'id_promotion', type: 'integer', example: 18),
    new OA\Property(property: 'id_annee_academique', type: 'integer', example: 14),
    new OA\Property(property: 'evaluation', type: 'string', maxLength: 100, default: 'principale', example: 'devoir_1', description: 'Evaluation par defaut des lignes sans evaluation. Envoyer toutes les evaluations dans une seule requete : enregistrement et transmission immediats, puis verrouillage. Une seule note par couple etudiant/evaluation. Moyenne simple des notes. Apres transmission, modification uniquement apres refus du secretariat ou via correction autorisee.'),
    new OA\Property(property: 'notes', type: 'array', minItems: 1, items: new OA\Items(type: 'object', required: ['id_etudiant', 'note'], properties: [
        new OA\Property(property: 'evaluation', type: 'string', maxLength: 100, example: 'examen', description: 'Prioritaire sur evaluation a la racine. Permet plusieurs notes du meme etudiant dans une requete, avec des evaluations distinctes.'),
        new OA\Property(property: 'id_etudiant', type: 'integer', example: 4),
        new OA\Property(property: 'note', type: 'number', minimum: 0, maximum: 20, nullable: true, example: 15.5),
    ])),
])]
#[OA\Get(path: '/enseignant/notes/matieres/{matiere}', operationId: 'consulterNotesMatiereExplicite', summary: 'Consulter la feuille par matiere',
    description: 'La matiere est indiquee dans le chemin. Fournir id_seance, id_promotion et id_annee_academique. PUT corrige et retransmet la meme feuille apres rejet du secretariat ou de la direction. Les notes sont ensuite verrouillees.',
    tags: ['Saisie des notes'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'matiere', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_seance', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_promotion', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_annee_academique', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [new OA\Response(response: 200, description: 'Feuille de notes.', content: new OA\JsonContent(ref: '#/components/schemas/NotesCoursReponse')), new OA\Response(response: 404, description: 'Matiere inaccessible.'), new OA\Response(response: 422, description: 'Contexte invalide ou saisie fermee.')])]
#[OA\Put(path: '/enseignant/notes/matieres/{matiere}', operationId: 'modifierNotesMatiereExplicite', summary: 'Modifier et retransmettre une feuille par matiere',
    description: 'La matiere est indiquee dans le chemin. Fournir id_seance, id_promotion et id_annee_academique. PUT corrige et retransmet la meme feuille apres rejet du secretariat ou de la direction. Les notes sont ensuite verrouillees.',
    tags: ['Saisie des notes'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'matiere', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object', required: ['id_seance', 'id_promotion', 'id_annee_academique', 'notes'], properties: [
        new OA\Property(property: 'id_seance', type: 'integer'),
        new OA\Property(property: 'id_promotion', type: 'integer'),
        new OA\Property(property: 'id_annee_academique', type: 'integer'),
        new OA\Property(property: 'notes', type: 'array', minItems: 1, items: new OA\Items(type: 'object', required: ['id_etudiant', 'note'], properties: [new OA\Property(property: 'id_etudiant', type: 'integer'), new OA\Property(property: 'evaluation', type: 'string'), new OA\Property(property: 'note', type: 'number', minimum: 0, maximum: 20)])),
    ])),
    responses: [new OA\Response(response: 200, description: 'Feuille de notes.', content: new OA\JsonContent(ref: '#/components/schemas/NotesCoursReponse')), new OA\Response(response: 404, description: 'Matiere inaccessible.'), new OA\Response(response: 422, description: 'Contexte invalide ou saisie fermee.')])]
#[OA\Post(path: '/enseignant/notes/matieres/{matiere}/transmettre', operationId: 'transmettreNotesMatiereExplicite', summary: 'Transmettre une feuille par matiere',
    description: 'La matiere est indiquee dans le chemin. Fournir id_seance, id_promotion et id_annee_academique. PUT corrige et retransmet la meme feuille apres rejet du secretariat ou de la direction. Les notes sont ensuite verrouillees.',
    tags: ['Saisie des notes'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'matiere', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object', required: ['id_seance', 'id_promotion', 'id_annee_academique'], properties: [
        new OA\Property(property: 'id_seance', type: 'integer'),
        new OA\Property(property: 'id_promotion', type: 'integer'),
        new OA\Property(property: 'id_annee_academique', type: 'integer'),
        new OA\Property(property: 'notes', type: 'array', minItems: 1, items: new OA\Items(type: 'object', required: ['id_etudiant', 'note'], properties: [new OA\Property(property: 'id_etudiant', type: 'integer'), new OA\Property(property: 'evaluation', type: 'string'), new OA\Property(property: 'note', type: 'number', minimum: 0, maximum: 20)])),
    ])),
    responses: [new OA\Response(response: 200, description: 'Feuille de notes.', content: new OA\JsonContent(ref: '#/components/schemas/NotesCoursReponse')), new OA\Response(response: 404, description: 'Matiere inaccessible.'), new OA\Response(response: 422, description: 'Contexte invalide ou saisie fermee.')])]
#[OA\Get(path: '/enseignant/notes/feuille', operationId: 'enseignantAfficherNotesMatiere', summary: 'Consulter les étudiants et notes de la matière par promotion',
    description: 'Fournir id_matiere, id_promotion, id_annee_academique et id_seance en query pour consulter la feuille de cette seance. Retourne etudiants, notes, moyenne matiere et eligibilite selon uniquement les presences de cette seance. Sans id_seance, consulte les anciennes feuilles sans seance. cours et module sont null.',
    tags: ['Saisie des notes'], security: [['sanctum' => []]], parameters: [
        new OA\Parameter(name: 'id_seance', in: 'query', required: false, description: 'Seance a consulter. Sans ce parametre, consulte uniquement les anciennes feuilles sans seance.', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_matiere', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_promotion', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_annee_academique', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
    ], responses: [new OA\Response(response: 200, description: 'feuille_notes avec étudiants et notes par matière ; cours et module sont null.', content: new OA\JsonContent(ref: '#/components/schemas/NotesCoursReponse')), new OA\Response(response: 401, description: 'Authentification requise.'), new OA\Response(response: 403, description: 'Compte enseignant actif requis.'), new OA\Response(response: 404, description: 'Matière ou contexte inaccessible.'), new OA\Response(response: 422, description: 'Contexte invalide.')])]
#[OA\Put(path: '/enseignant/notes', operationId: 'enseignantEnregistrerNotesMatiere', summary: 'Saisir des notes par matière sans cours',
    description: 'id_seance est obligatoire. Enregistrement et transmission immediats pour cette seance, sans brouillon. Ses presences doivent etre validees et couvrir tous les etudiants de la promotion. Tous les etudiants evaluables sur cette seance doivent avoir une note. Envoyer toutes ses evaluations ensemble dans notes[].evaluation. La feuille de cette seance est ensuite verrouillee ; les autres seances restent independantes.',
    tags: ['Saisie des notes'], security: [['sanctum' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(allOf: [new OA\Schema(ref: '#/components/schemas/NotesMatierePayload'), new OA\Schema(required: ['notes'])], example: [
        'id_seance' => 157, 'id_matiere' => 21, 'id_promotion' => 22, 'id_annee_academique' => 16,
        'notes' => [
            ['id_etudiant' => 23, 'evaluation' => 'devoir_1', 'note' => 15.5],
            ['id_etudiant' => 23, 'evaluation' => 'examen', 'note' => 17],
            ['id_etudiant' => 26, 'evaluation' => 'devoir_1', 'note' => 12],
            ['id_etudiant' => 26, 'evaluation' => 'examen', 'note' => 14],
        ],
    ])),
    responses: [new OA\Response(response: 200, description: 'Notes enregistrees et directement transmises. statut=transmise, saisie_ouverte=false.', content: new OA\JsonContent(ref: '#/components/schemas/NotesCoursReponse')), new OA\Response(response: 404, description: 'Matière ou contexte inaccessible.'), new OA\Response(response: 422, description: 'Notes invalides, feuille incomplete ou saisie fermee. Aucun changement conserve.')])]
#[OA\Post(path: '/enseignant/notes/transmettre', operationId: 'enseignantTransmettreNotesMatiere', summary: 'Transmettre les notes par matière',
    description: 'id_seance est obligatoire. Alternative a PUT : enregistrer et transmettre toutes les evaluations de la seance ensemble. notes est facultatif si la feuille existe deja. Sans nouvelles notes, une feuille deja transmise est retournee sans nouvel historique. Toute saisie exige les presences validees de cette seance et au moins une note par etudiant evaluable. Apres succes sa feuille est verrouillee.',
    tags: ['Saisie des notes'], security: [['sanctum' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/NotesMatierePayload')),
    responses: [new OA\Response(response: 200, description: 'Feuille transmise.'), new OA\Response(response: 404, description: 'Matière ou contexte inaccessible.'), new OA\Response(response: 422, description: 'Feuille incomplète ou saisie fermée.')])]
#[OA\Get(
    path: '/enseignant/notes/tableau', operationId: 'enseignantTableauNotesMatiere',
    summary: 'Recuperer uniquement le tableau des notes par matiere',
    description: 'Tableau JSON directement a la racine, sans feuille_notes ni contexte. Une ligne par etudiant et evaluation. Retourne [] si aucune note n est enregistree. Meme controle d affectation active et de creneau que la consultation de la feuille.',
    tags: ['Saisie des notes'], security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'id_seance', in: 'query', required: false, description: 'Seance a consulter. Sans ce parametre, consulte uniquement les anciennes feuilles sans seance.', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_matiere', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_promotion', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_annee_academique', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Tableau des notes enregistrees.', content: new OA\JsonContent(type: 'array', items: new OA\Items(type: 'object', properties: [
            new OA\Property(property: 'id', type: 'integer', example: 1),
            new OA\Property(property: 'id_etudiant', type: 'integer', example: 23),
            new OA\Property(property: 'evaluation', type: 'string', example: 'examen'),
            new OA\Property(property: 'note', type: 'number', example: 15.5),
        ]))),
        new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 403, description: 'Compte enseignant actif requis.'),
        new OA\Response(response: 404, description: 'Matiere ou contexte inaccessible.'),
        new OA\Response(response: 422, description: 'Identifiants manquants ou invalides.'),
    ]
)]
final class NotesMatiereEnseignantDocumentation {}
