<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/etudiant/mes-matieres',
    operationId: 'listerMesMatieresEtudiant',
    summary: 'Lister les matières accessibles à l’étudiant connecté',
    description: 'Les matières sont déterminées par la promotion et le niveau actuels. L’accès est cumulatif : un étudiant de deuxième année voit les matières des première et deuxième années. Seules les notes de bulletins publiés sont exposées.',
    tags: ['Espace étudiant'], security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'recherche', in: 'query', schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'statut', in: 'query', schema: new OA\Schema(type: 'string', enum: ['validee', 'a_completer', 'non_validee'])),
        new OA\Parameter(name: 'id_niveau', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100)),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Liste paginée des matières et notes publiées.'),
        new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 403, description: 'Rôle étudiant requis.'),
        new OA\Response(response: 404, description: 'Fiche étudiant ou inscription introuvable.'),
        new OA\Response(response: 422, description: 'Filtres invalides.'),
    ]
)]
#[OA\Get(
    path: '/etudiant/mes-matieres/{matiere}',
    operationId: 'afficherMaMatiereEtudiant',
    summary: 'Afficher le détail d’une matière accessible à l’étudiant',
    description: 'Retourne la matière, sa note publiée et ses modules/cours. Les notes par cours restent nulles tant qu’aucune donnée d’évaluation par cours n’existe.',
    tags: ['Espace étudiant'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'matiere', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [
        new OA\Response(response: 200, description: 'Détail de la matière.'),
        new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 403, description: 'Rôle étudiant requis.'),
        new OA\Response(response: 404, description: 'Matière inaccessible ou introuvable.'),
    ]
)]
final class MesMatieresEtudiantDocumentation {}
