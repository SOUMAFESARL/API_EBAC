<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Corrections des notes', description: 'Circuit : demande enseignant, verification par le secretariat, puis application directe ou rejet avec motif. Aucune autorisation de la direction requise. POST /{id}/appliquer autorise et applique atomiquement une demande en_attente pour ADMIN, SECRETARIAT ou SECRETAIRE_ACADEMIQUE. Les decisions sont tracees. Les routes historiques restent disponibles.')]
#[OA\Schema(schema: 'DemandeCorrectionNote', required: ['id_seance', 'id_note', 'note_proposee', 'motif'], properties: [
    new OA\Property(property: 'id_seance', type: 'integer', description: 'Obligatoire. Doit correspondre a la seance de toutes les notes demandees.', example: 1),
    new OA\Property(property: 'id_note', type: 'integer', description: 'Identifiant de la note, exposé dans feuille_notes.etudiants[].id_note, pour une feuille par matière comme par cours. Pour une matière : GET /enseignant/notes/feuille avec id_matiere, id_promotion et id_annee_academique. Aucun id_cours ni id_matiere à fournir dans la demande de correction : id_note identifie déjà le contexte. La feuille doit être transmise.', example: 1),
    new OA\Property(property: 'note_proposee', type: 'number', minimum: 0, maximum: 20, description: 'Deux décimales maximum, différente de la note actuelle.', example: 15.5),
    new OA\Property(property: 'motif', type: 'string', maxLength: 5000, example: 'Erreur de saisie sur la copie corrigée'),
])]
#[OA\Get(path: '/administration/corrections-notes', summary: 'Lister les demandes de correction', tags: ['Corrections des notes'], security: [['sanctum' => []]], parameters: [
    new OA\Parameter(name: 'id_note', in: 'query', schema: new OA\Schema(type: 'integer')),
    new OA\Parameter(name: 'id_seance', in: 'query', schema: new OA\Schema(type: 'integer')),
    new OA\Parameter(name: 'demande_par', in: 'query', description: 'Identifiant du demandeur.', schema: new OA\Schema(type: 'integer')),
    new OA\Parameter(name: 'search', in: 'query', description: 'Recherche par demandeur, etudiant (nom, prenoms, matricule), matiere ou cours.', schema: new OA\Schema(type: 'string', maxLength: 255)),
    new OA\Parameter(name: 'statut', in: 'query', schema: new OA\Schema(type: 'string', enum: ['en_attente', 'transmise_direction', 'autorisee', 'appliquee', 'rejetee'])),
    new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 20)),
    new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer')),
], responses: [new OA\Response(response: 200, description: 'Pagination Laravel : data, current_page, total et liens. Chaque correction inclut demandeur (id, nom, prenoms, role), etudiant, evaluation, feuille_notes (matiere, cours, promotion et niveau), actions (peut_valider, peut_rejeter, peut_transmettre_direction, peut_appliquer). peut_appliquer est vrai pour le secretariat et ADMIN des en_attente ; peut_transmettre_direction est faux. Une ligne par note corrigee.'), new OA\Response(response: 401, description: 'Non authentifié.'), new OA\Response(response: 403, description: 'Accès interdit.')])]
#[OA\Post(path: '/administration/corrections-notes', summary: 'Demander une correction de note transmise', tags: ['Corrections des notes'], security: [['sanctum' => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/DemandeCorrectionNote')), responses: [
    new OA\Response(response: 201, description: 'message et correction avec id, note_initiale, note_proposee, motif, demande_par, statut en_attente et dates.'),
    new OA\Response(response: 404, description: 'Note introuvable.'),
    new OA\Response(response: 422, description: 'Valeur invalide, note inchangée, feuille non transmise ou correction déjà en cours.'),
])]
#[OA\Get(path: '/administration/corrections-notes/{id}', summary: 'Consulter une correction et son historique', tags: ['Corrections des notes'], security: [['sanctum' => []]], parameters: [
    new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
], responses: [new OA\Response(response: 200, description: 'correction et historique chronologique : id_acteur, action, details, created_at.'), new OA\Response(response: 404, description: 'Correction introuvable.')])]
#[OA\Post(path: '/administration/corrections-notes/{id}/autoriser', summary: 'Autoriser une correction (etape facultative avant application)', tags: ['Corrections des notes'], security: [['sanctum' => []]], parameters: [
    new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
], responses: [new OA\Response(response: 200, description: 'message et correction autorisee ; aucune modification de note à cette étape.'), new OA\Response(response: 403, description: 'Rôle non autorisé.'), new OA\Response(response: 404, description: 'Correction introuvable.'), new OA\Response(response: 422, description: 'Statut incompatible.')])]
#[OA\Post(path: '/administration/corrections-notes/{id}/valider', summary: 'Autoriser une demande de correction',
    description: 'Alias de /autoriser. ADMIN, SECRETARIAT, SECRETAIRE_ACADEMIQUE ou DIRECTION : en_attente ou transmise_direction vers autorisee. Etape facultative : le secretariat peut directement appeler /appliquer.',
    tags: ['Corrections des notes'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'message et correction autorisee.'),
        new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 403, description: 'Role interdit ou compte inactif.'),
        new OA\Response(response: 404, description: 'Correction introuvable.'),
        new OA\Response(response: 422, description: 'Demande deja autorisee, appliquee ou rejetee.')])]
#[OA\Post(path: '/administration/corrections-notes/{id}/rejeter', summary: 'Rejeter une demande : en_attente ou ancien statut transmise_direction', tags: ['Corrections des notes'], security: [['sanctum' => []]], parameters: [
    new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['motif'], properties: [new OA\Property(property: 'motif', type: 'string', maxLength: 5000, example: 'La copie ne justifie pas cette correction.')])), responses: [new OA\Response(response: 200, description: 'message et correction rejetee ; motif conservé dans l’historique.'), new OA\Response(response: 403, description: 'Rôle non autorisé.'), new OA\Response(response: 404, description: 'Correction introuvable.'), new OA\Response(response: 422, description: 'Motif manquant ou statut incompatible.')])]
#[OA\Post(path: '/administration/corrections-notes/{id}/appliquer', summary: 'Verifier et appliquer directement une correction (ADMIN, secretariat)', description: 'Sans corps. ADMIN, SECRETARIAT et SECRETAIRE_ACADEMIQUE peuvent appliquer directement une demande en_attente, autorisee ou dans l ancien statut transmise_direction. Aucune autorisation de direction requise. Verifie la note initiale, autorise si necessaire puis met a jour la note dans une transaction. Trace acteur et dates. La reponse contient correction : note_initiale, note_proposee, note_actuelle, note_finale, statut appliquee, autorisee_par, appliquee_par, dates, etudiant, demandeur, evaluation, feuille_notes et actions actualisees. Refuse une seconde application ou une note modifiee depuis la demande.', tags: ['Corrections des notes'], security: [['sanctum' => []]], parameters: [
    new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
], responses: [new OA\Response(response: 200, description: 'message et correction appliquee avec note_finale, appliquee_par et appliquee_le. Mise à jour atomique de la note et de l’historique.'), new OA\Response(response: 403, description: 'Rôle non autorisé.'), new OA\Response(response: 404, description: 'Correction introuvable.'), new OA\Response(response: 422, description: 'Statut incompatible, feuille non transmise ou note modifiée depuis la demande. Une correction ne peut être appliquée deux fois.')])]
#[OA\Post(path: '/administration/corrections-notes/{id}/transmettre-direction',
    deprecated: true,
    summary: 'Ancien circuit : transmission direction (facultatif)',
    description: 'Sans corps. Passe de en_attente a transmise_direction et trace acteur et date. Ne modifie pas la note. La direction peut ensuite valider ou rejeter.',
    tags: ['Corrections des notes'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'message et correction transmise_direction.'),
        new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 403, description: 'Role interdit ou compte inactif.'),
        new OA\Response(response: 404, description: 'Correction introuvable.'),
        new OA\Response(response: 422, description: 'Statut incompatible.')])]
final class CorrectionsNotesDocumentation {}
