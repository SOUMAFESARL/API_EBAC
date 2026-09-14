<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/enseignant/emploi-du-temps',
    operationId: 'afficherEmploiDuTempsEnseignant',
    summary: 'Afficher l’emploi du temps hebdomadaire de l’enseignant connecté',
    description: 'Retourne exclusivement les créneaux publiés attribués au compte enseignant authentifié, groupés par jour. Tant que l’administrateur n’a pas publié le programme, aucun créneau n’est exposé.',
    tags: ['Espace enseignant'],
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'id_annee_academique', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_module_calendrier', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Emploi du temps, jours, créneaux et total hebdomadaire.'),
        new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 403, description: 'Rôle enseignant requis.'),
        new OA\Response(response: 422, description: 'Année ou module calendrier invalide.'),
    ]
)]
final class EmploiDuTempsEnseignantDocumentation {}
