<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'DemandesCorrectionsNotesGroupees', type: 'object', required: ['id_seance', 'motif', 'notes'], properties: [
    new OA\Property(property: 'id_seance', type: 'integer', description: 'Obligatoire. Doit correspondre a la seance de toutes les notes demandees.', example: 1),
    new OA\Property(property: 'motif', type: 'string', maxLength: 5000, example: 'Erreur de report des notes'),
    new OA\Property(property: 'notes', type: 'array', minItems: 1, maxItems: 100,
        items: new OA\Items(type: 'object', required: ['id_note', 'note_proposee'], properties: [
            new OA\Property(property: 'id_note', type: 'integer', example: 12),
            new OA\Property(property: 'note_proposee', type: 'number', minimum: 0, maximum: 20, example: 16),
        ])),
])]
#[OA\Get(path: '/enseignant/corrections-notes', summary: 'Lister mes demandes de correction',
    tags: ['Corrections des notes'], security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'id_note', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_seance', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'search', in: 'query', description: 'Nom, prenoms ou matricule de l etudiant, libelle de matiere ou de cours.', schema: new OA\Schema(type: 'string', maxLength: 255)),
        new OA\Parameter(name: 'statut', in: 'query', schema: new OA\Schema(type: 'string', enum: ['en_attente', 'autorisee', 'appliquee', 'rejetee'])),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100)),
    ], responses: [new OA\Response(response: 200, description: 'Demandes du seul enseignant connecte, pagination Laravel. Chaque ligne contient etudiant, evaluation, modifiable et feuille_notes (matiere, cours, promotion et niveau).'),
        new OA\Response(response: 401, description: 'Authentification requise.'), new OA\Response(response: 403, description: 'Role ENSEIGNANT requis.')])]
#[OA\Post(path: '/enseignant/corrections-notes', summary: 'Demander la modification d une ou plusieurs notes transmises',
    description: 'Format unitaire : id_note, id_seance obligatoire, note_proposee, motif. Format groupe : id_seance obligatoire commun a toutes les notes. Chaque correction retournee contient id_seance, null pour une ancienne feuille sans seance. Format groupe : motif commun et notes contenant jusqu a 100 couples id_note/note_proposee distincts. Les formats ne peuvent pas etre melanges. id_note est fourni par la feuille de notes. Chaque note doit etre accessible a l enseignant et transmise. Si une ligne echoue, aucune demande ni trace n est creee. Les notes restent inchangees. Chaque correction retournee doit etre autorisee par l administration puis appliquee par l enseignant.',
    tags: ['Corrections des notes'], security: [['sanctum' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(oneOf: [
        new OA\Schema(ref: '#/components/schemas/DemandeCorrectionNote'),
        new OA\Schema(ref: '#/components/schemas/DemandesCorrectionsNotesGroupees'),
    ])),
    responses: [new OA\Response(response: 201, description: 'Format unitaire : correction. Format groupe : corrections et nombre_demandes. Toutes les demandes sont en_attente.'),
        new OA\Response(response: 404, description: 'Note inexistante ou enseignement non affecte.'),
        new OA\Response(response: 422, description: 'Motif ou note invalide, feuille non transmise ou demande deja en cours.')])]
#[OA\Get(path: '/enseignant/corrections-notes/{id}', summary: 'Consulter ma demande et son historique',
    tags: ['Corrections des notes'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'correction avec etudiant, evaluation, modifiable, feuille_notes et historique.'), new OA\Response(response: 404, description: 'Demande inexistante ou appartenant a un autre enseignant.')])]
#[OA\Put(path: '/enseignant/corrections-notes/{id}', summary: 'Modifier ma demande de correction en attente',
    description: 'Seul le demandeur peut modifier note_proposee et motif tant que le statut est en_attente et que son affectation est active. La note et la seance de la demande restent fixes. La note initiale reste inchangee. Chaque modification est tracee avec les anciennes et nouvelles valeurs. Pour une creation groupee, appeler cette route pour chaque identifiant de correction retourne.',
    tags: ['Corrections des notes'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object', required: ['note_proposee', 'motif'], properties: [
        new OA\Property(property: 'note_proposee', type: 'number', minimum: 0, maximum: 20, example: 16.5),
        new OA\Property(property: 'motif', type: 'string', maxLength: 5000, example: 'Copies recomptees'),
    ])),
    responses: [new OA\Response(response: 200, description: 'Demande modifiee, correction et historique mis a jour.'),
        new OA\Response(response: 404, description: 'Demande d un autre enseignant ou affectation non autorisee.'),
        new OA\Response(response: 422, description: 'Demande deja tranchee, note ou motif invalide, ou note initiale modifiee.')])]
#[OA\Post(path: '/enseignant/corrections-notes/{id}/appliquer', summary: 'Modifier la note apres validation de ma demande',
    description: 'Sans corps : applique uniquement note_proposee autorisee, pour la note de la demande. La feuille reste transmise et verrouillee. Une demande ne peut etre appliquee qu une seule fois. Une nouvelle valeur necessite une nouvelle demande.',
    tags: ['Corrections des notes'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Note modifiee, correction appliquee et historique mis a jour.'),
        new OA\Response(response: 404, description: 'Demande d un autre enseignant ou affectation non autorisee.'),
        new OA\Response(response: 422, description: 'Demande non autorisee, rejetee, deja appliquee ou note modifiee depuis la demande.')])]
final class CorrectionsNotesEnseignantDocumentation {}
