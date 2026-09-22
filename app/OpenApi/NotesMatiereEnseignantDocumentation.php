<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'NotesMatierePayload', type: 'object', required: ['id_matiere', 'id_promotion', 'id_annee_academique'], properties: [
    new OA\Property(property: 'id_matiere', type: 'integer', example: 19),
    new OA\Property(property: 'id_promotion', type: 'integer', example: 18),
    new OA\Property(property: 'id_annee_academique', type: 'integer', example: 14),
    new OA\Property(property: 'notes', type: 'array', minItems: 1, items: new OA\Items(type: 'object', required: ['id_etudiant', 'note'], properties: [
        new OA\Property(property: 'id_etudiant', type: 'integer', example: 4),
        new OA\Property(property: 'note', type: 'number', minimum: 0, maximum: 20, nullable: true, example: 15.5),
    ])),
])]
#[OA\Get(path: '/enseignant/notes/feuille', operationId: 'enseignantAfficherNotesMatiere', summary: 'Consulter les notes par matière sans cours',
    tags: ['Saisie des notes'], security: [['sanctum' => []]], parameters: [
        new OA\Parameter(name: 'id_matiere', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_promotion', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_annee_academique', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
    ], responses: [new OA\Response(response: 200, description: 'Feuille par matière ; cours et module sont null.'), new OA\Response(response: 404, description: 'Matière ou contexte inaccessible.'), new OA\Response(response: 422, description: 'Contexte invalide.')])]
#[OA\Put(path: '/enseignant/notes', operationId: 'enseignantEnregistrerNotesMatiere', summary: 'Saisir des notes par matière sans cours',
    description: 'notes est obligatoire. Les présences doivent être validées pour toutes les séances réalisées de la matière dans cette promotion et cette année. Les notes par cours restent distinctes.',
    tags: ['Saisie des notes'], security: [['sanctum' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(allOf: [new OA\Schema(ref: '#/components/schemas/NotesMatierePayload'), new OA\Schema(required: ['notes'])])),
    responses: [new OA\Response(response: 200, description: 'Notes enregistrées et feuille actualisée.'), new OA\Response(response: 404, description: 'Matière ou contexte inaccessible.'), new OA\Response(response: 422, description: 'Notes invalides ou saisie fermée.')])]
#[OA\Post(path: '/enseignant/notes/transmettre', operationId: 'enseignantTransmettreNotesMatiere', summary: 'Transmettre les notes par matière',
    description: 'notes est facultatif : transmettre les notes enregistrées ou enregistrer et transmettre en une transaction. Tous les étudiants évaluables doivent avoir une note. La feuille est ensuite verrouillée.',
    tags: ['Saisie des notes'], security: [['sanctum' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/NotesMatierePayload')),
    responses: [new OA\Response(response: 200, description: 'Feuille transmise.'), new OA\Response(response: 404, description: 'Matière ou contexte inaccessible.'), new OA\Response(response: 422, description: 'Feuille incomplète ou saisie fermée.')])]
final class NotesMatiereEnseignantDocumentation {}
