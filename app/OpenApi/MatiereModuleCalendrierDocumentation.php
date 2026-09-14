<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'MatiereModuleCalendrierSelection',
    description: 'Champ complémentaire accepté par POST, PUT et PATCH du CRUD des matières.',
    type: 'object',
    properties: [
        new OA\Property(
            property: 'module_calendrier_id',
            description: 'Identifiants uniques des modules du calendrier associés. Envoyer un tableau vide lors d’une modification pour retirer toutes les associations.',
            type: 'array',
            items: new OA\Items(type: 'integer'),
            example: [1, 2],
        ),
    ],
)]
final class MatiereModuleCalendrierDocumentation {}
