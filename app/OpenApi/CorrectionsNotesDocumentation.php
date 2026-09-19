<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Corrections des notes', description: 'Correction des notes transmises : demande, autorisation puis application. Accès ADMIN, SECRETARIAT, SECRETAIRE_ACADEMIQUE et DIRECTION avec un compte actif. Autoriser et rejeter : ADMIN ou DIRECTION. Appliquer : ADMIN ou secrétariat. Les brouillons restent modifiables via la saisie enseignant. La feuille reste transmise et les moyennes consultées reflètent la note corrigée. Chaque étape conserve son acteur et sa date dans l’historique.')]
#[OA\Schema(schema: 'DemandeCorrectionNote', required: ['id_note', 'note_proposee', 'motif'], properties: [
    new OA\Property(property: 'id_note', type: 'integer', description: 'Identifiant notes_cours, exposé dans etudiants[].id_note de la feuille enseignant.', example: 1),
    new OA\Property(property: 'note_proposee', type: 'number', minimum: 0, maximum: 20, description: 'Deux décimales maximum, différente de la note actuelle.', example: 15.5),
    new OA\Property(property: 'motif', type: 'string', maxLength: 5000, example: 'Erreur de saisie sur la copie corrigée'),
])]
#[OA\Get(path: '/administration/corrections-notes', summary: 'Lister les demandes de correction', tags: ['Corrections des notes'], security: [['sanctum' => []]], parameters: [
    new OA\Parameter(name: 'id_note', in: 'query', schema: new OA\Schema(type: 'integer')),
    new OA\Parameter(name: 'statut', in: 'query', schema: new OA\Schema(type: 'string', enum: ['en_attente', 'autorisee', 'appliquee', 'rejetee'])),
    new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 20)),
    new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer')),
], responses: [new OA\Response(response: 200, description: 'Pagination Laravel : data, current_page, total et liens.'), new OA\Response(response: 401, description: 'Non authentifié.'), new OA\Response(response: 403, description: 'Accès interdit.')])]
#[OA\Post(path: '/administration/corrections-notes', summary: 'Demander une correction de note transmise', tags: ['Corrections des notes'], security: [['sanctum' => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/DemandeCorrectionNote')), responses: [
    new OA\Response(response: 201, description: 'message et correction avec id, note_initiale, note_proposee, motif, demande_par, statut en_attente et dates.'),
    new OA\Response(response: 404, description: 'Note introuvable.'),
    new OA\Response(response: 422, description: 'Valeur invalide, note inchangée, feuille non transmise ou correction déjà en cours.'),
])]
#[OA\Get(path: '/administration/corrections-notes/{id}', summary: 'Consulter une correction et son historique', tags: ['Corrections des notes'], security: [['sanctum' => []]], parameters: [
    new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
], responses: [new OA\Response(response: 200, description: 'correction et historique chronologique : id_acteur, action, details, created_at.'), new OA\Response(response: 404, description: 'Correction introuvable.')])]
#[OA\Post(path: '/administration/corrections-notes/{id}/autoriser', summary: 'Autoriser une demande en attente (ADMIN, DIRECTION)', tags: ['Corrections des notes'], security: [['sanctum' => []]], parameters: [
    new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
], responses: [new OA\Response(response: 200, description: 'message et correction autorisee ; aucune modification de note à cette étape.'), new OA\Response(response: 403, description: 'Rôle non autorisé.'), new OA\Response(response: 404, description: 'Correction introuvable.'), new OA\Response(response: 422, description: 'Statut incompatible.')])]
#[OA\Post(path: '/administration/corrections-notes/{id}/rejeter', summary: 'Rejeter une demande en attente (ADMIN, DIRECTION)', tags: ['Corrections des notes'], security: [['sanctum' => []]], parameters: [
    new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['motif'], properties: [new OA\Property(property: 'motif', type: 'string', maxLength: 5000, example: 'La copie ne justifie pas cette correction.')])), responses: [new OA\Response(response: 200, description: 'message et correction rejetee ; motif conservé dans l’historique.'), new OA\Response(response: 403, description: 'Rôle non autorisé.'), new OA\Response(response: 404, description: 'Correction introuvable.'), new OA\Response(response: 422, description: 'Motif manquant ou statut incompatible.')])]
#[OA\Post(path: '/administration/corrections-notes/{id}/appliquer', summary: 'Appliquer une correction autorisée (ADMIN, secrétariat)', tags: ['Corrections des notes'], security: [['sanctum' => []]], parameters: [
    new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
], responses: [new OA\Response(response: 200, description: 'message et correction appliquee avec note_finale, appliquee_par et appliquee_le. Mise à jour atomique de la note et de l’historique.'), new OA\Response(response: 403, description: 'Rôle non autorisé.'), new OA\Response(response: 404, description: 'Correction introuvable.'), new OA\Response(response: 422, description: 'Statut incompatible, feuille non transmise ou note modifiée depuis la demande. Une correction ne peut être appliquée deux fois.')])]
final class CorrectionsNotesDocumentation {}
