<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'ProgrammationRattrapagePayload', type: 'object', required: ['id_cours_a_faire', 'date_prevue', 'heure_debut', 'heure_fin', 'enseignant_id'], properties: [
    new OA\Property(property: 'id_cours_a_faire', type: 'integer', description: 'Identifiant obtenu via GET /administration/rattrapages/cours-a-rattraper. Interdit en modification : la cible reste identique.', example: 1),
    new OA\Property(property: 'date_prevue', type: 'string', format: 'date', example: '2026-10-15'),
    new OA\Property(property: 'heure_debut', type: 'string', example: '08:00'),
    new OA\Property(property: 'heure_fin', type: 'string', description: 'HH:mm, strictement apres heure_debut.', example: '10:00'),
    new OA\Property(property: 'enseignant_id', type: 'integer', description: 'Compte actif de role ENSEIGNANT.', example: 2),
    new OA\Property(property: 'id_salle', type: 'integer', nullable: true),
    new OA\Property(property: 'statut', type: 'string', enum: ['programme', 'realise', 'annule'], default: 'programme', description: 'Statut de programmation uniquement. Ne modifie ni la presence initiale ni le statut du cours a faire.'),
    new OA\Property(property: 'observations', type: 'string', nullable: true, maxLength: 5000),
])]
#[OA\Schema(schema: 'ModificationRattrapagePayload', type: 'object', required: ['date_prevue', 'heure_debut', 'heure_fin', 'enseignant_id'], properties: [
    new OA\Property(property: 'date_prevue', type: 'string', format: 'date', example: '2026-10-16'),
    new OA\Property(property: 'heure_debut', type: 'string', example: '08:00'),
    new OA\Property(property: 'heure_fin', type: 'string', example: '10:00'),
    new OA\Property(property: 'enseignant_id', type: 'integer', example: 2),
    new OA\Property(property: 'id_salle', type: 'integer', nullable: true),
    new OA\Property(property: 'statut', type: 'string', enum: ['programme', 'realise', 'annule']),
    new OA\Property(property: 'observations', type: 'string', nullable: true, maxLength: 5000),
])]
#[OA\Get(path: '/administration/rattrapages', operationId: 'listerRattrapages', summary: 'Lister les programmations de rattrapage', description: 'Roles ADMIN, SECRETARIAT, SECRETAIRE_ACADEMIQUE. Pagination Laravel data, total, current_page. Relations cours_a_faire (etudiant, seance, cours, matiere), enseignant et salle.', tags: ['Rattrapages'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'id_etudiant', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)), new OA\Parameter(name: 'id_seance', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)), new OA\Parameter(name: 'id_cours', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)), new OA\Parameter(name: 'id_matiere', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)), new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)), new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)), new OA\Parameter(name: 'statut', in: 'query', schema: new OA\Schema(type: 'string', enum: ['programme', 'realise', 'annule'])), new OA\Parameter(name: 'enseignant_id', in: 'query', schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'date_prevue', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'))],
    responses: [new OA\Response(response: 200, description: 'Succes.'), new OA\Response(response: 401, description: 'Authentification requise.'), new OA\Response(response: 403, description: 'Role non autorise.'), new OA\Response(response: 404, description: 'Ressource introuvable.'), new OA\Response(response: 422, description: 'Donnees invalides, absence non eligible ou programmation existante.')]) ]
#[OA\Get(path: '/administration/rattrapages/cours-a-rattraper', operationId: 'listerAbsencesARattraper', summary: 'Lister les absences a rattraper', description: 'Uniquement les cours a_faire issus des absences actuelles sur des feuilles validees et seances realisees. Inclut la programmation existante dans rattrapage.', tags: ['Rattrapages'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'id_etudiant', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)), new OA\Parameter(name: 'id_seance', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)), new OA\Parameter(name: 'id_cours', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)), new OA\Parameter(name: 'id_matiere', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)), new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)), new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1))],
    responses: [new OA\Response(response: 200, description: 'Succes.'), new OA\Response(response: 401, description: 'Authentification requise.'), new OA\Response(response: 403, description: 'Role non autorise.'), new OA\Response(response: 404, description: 'Ressource introuvable.'), new OA\Response(response: 422, description: 'Donnees invalides, absence non eligible ou programmation existante.')]) ]
#[OA\Post(path: '/administration/rattrapages', operationId: 'programmerRattrapage', summary: 'Programmer un rattrapage', description: 'Une programmation par cours a faire. Etudiant, seance, cours et matiere sont derives de cette absence, sans saisie manuelle. Date a partir de la seance manquee. La programmation est visible dans GET /etudiant/cours-a-faire.', tags: ['Rattrapages'], security: [['sanctum' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ProgrammationRattrapagePayload')),
    responses: [new OA\Response(response: 201, description: 'Succes.'), new OA\Response(response: 401, description: 'Authentification requise.'), new OA\Response(response: 403, description: 'Role non autorise.'), new OA\Response(response: 404, description: 'Ressource introuvable.'), new OA\Response(response: 422, description: 'Donnees invalides, absence non eligible ou programmation existante.')]) ]
#[OA\Get(path: '/administration/rattrapages/{id}', operationId: 'afficherRattrapage', summary: 'Afficher un rattrapage', description: 'Retourne rattrapage avec ses relations.', tags: ['Rattrapages'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Succes.'), new OA\Response(response: 401, description: 'Authentification requise.'), new OA\Response(response: 403, description: 'Role non autorise.'), new OA\Response(response: 404, description: 'Ressource introuvable.'), new OA\Response(response: 422, description: 'Donnees invalides, absence non eligible ou programmation existante.')]) ]
#[OA\Put(path: '/administration/rattrapages/{id}', operationId: 'modifierRattrapage', summary: 'Modifier une programmation', description: 'La cible id_cours_a_faire est immuable. Date et horaires et enseignant requis. Absence toujours validee et cours restant a faire requis.', tags: ['Rattrapages'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ModificationRattrapagePayload')),
    responses: [new OA\Response(response: 200, description: 'Succes.'), new OA\Response(response: 401, description: 'Authentification requise.'), new OA\Response(response: 403, description: 'Role non autorise.'), new OA\Response(response: 404, description: 'Ressource introuvable.'), new OA\Response(response: 422, description: 'Donnees invalides, absence non eligible ou programmation existante.')]) ]
#[OA\Delete(path: '/administration/rattrapages/{id}', operationId: 'supprimerRattrapage', summary: 'Supprimer une programmation', description: 'Conserve le cours a faire et la presence initiale. Permet une nouvelle programmation.', tags: ['Rattrapages'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Succes.'), new OA\Response(response: 401, description: 'Authentification requise.'), new OA\Response(response: 403, description: 'Role non autorise.'), new OA\Response(response: 404, description: 'Ressource introuvable.'), new OA\Response(response: 422, description: 'Donnees invalides, absence non eligible ou programmation existante.')]) ]
final class RattrapageDocumentation {}
