<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/enseignant/emploi-du-temps',
    operationId: 'afficherEmploiDuTempsEnseignant',
    summary: 'Afficher l’emploi du temps hebdomadaire de l’enseignant connecté',
    description: 'Retourne tous les créneaux attribués au compte enseignant authentifié dans l’année sélectionnée, pour toutes ses matières et tous les modules du calendrier, groupés par jour. Sans filtre de module, module_calendrier vaut null et aucun module courant n’est sélectionné automatiquement. id_module_calendrier permet de limiter explicitement les résultats à un module. Le calendrier académique doit être publié. Chaque créneau précise sa matière, son module et ses dates. Le total heures_hebdomadaires additionne les créneaux retournés, y compris ceux de modules ayant lieu à des périodes différentes.',
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
