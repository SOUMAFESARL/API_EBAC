<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/etudiant/cours-a-faire',
    operationId: 'listerMesCoursAFaire',
    summary: 'Lister les cours a rattraper de l etudiant connecte',
    description: 'Retourne uniquement ses entrees au statut a_faire, creees lors de la validation des absences, avec la matiere, le cours si renseigne et le contenu de la seance. Le champ rattrapage expose la programmation (date, horaires, enseignant, salle, statut), ou null.',
    tags: ['Espace étudiant'], security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100)),
    ],
    responses: [
        new OA\Response(response: 200, description: 'cours_a_faire et meta de pagination. Liste vide si aucun rattrapage.'),
        new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 403, description: 'Compte actif et role ETUDIANT requis.'),
        new OA\Response(response: 404, description: 'Fiche etudiant introuvable.'),
        new OA\Response(response: 422, description: 'Pagination invalide.'),
    ]
)]
final class MesCoursAFaireEtudiantDocumentation {}
