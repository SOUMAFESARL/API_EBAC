<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/enseignant/emploi-du-temps',
    operationId: 'afficherEmploiDuTempsEnseignant',
    summary: 'Afficher l’emploi du temps hebdomadaire de l’enseignant connecté',
    description: 'Retourne exclusivement les créneaux attribués au compte enseignant authentifié, groupés par jour. Sans module explicite, le module calendrier courant, puis le prochain disponible, est sélectionné.',
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
