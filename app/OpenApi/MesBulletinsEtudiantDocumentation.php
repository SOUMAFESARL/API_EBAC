<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Get(path: '/etudiant/bulletins', operationId: 'listerMesBulletins',
    summary: 'Lister les bulletins publies de l etudiant connecte',
    description: 'Authentification Sanctum, compte actif et role ETUDIANT requis. Seuls les bulletins au statut Publié avec date_publication sont visibles, tries par annee academique decroissante. Aucun identifiant etudiant n est accepte. Aucun brouillon ni bulletin provisoire calcule n est expose.',
    tags: ['Espace étudiant'], security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'id_annee_academique', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100)),
    ],
    responses: [new OA\Response(response: 200, description: 'etudiant, bulletins avec annee/promotion/niveau/moyenne/rang/mention et meta de pagination. Liste vide si aucun bulletin publie.'),
        new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 403, description: 'Compte actif et role ETUDIANT requis.'),
        new OA\Response(response: 404, description: 'Fiche etudiant introuvable.'),
        new OA\Response(response: 422, description: 'Filtres invalides.')]
)]
#[OA\Get(path: '/etudiant/bulletins/{id}', operationId: 'afficherMonBulletin',
    summary: 'Afficher le detail de mon bulletin publie',
    description: 'Retourne les lignes enregistrees du bulletin et leur coefficient, note, appreciation et statut selon le seuil de validation de la matiere. La moyenne, le rang et la mention proviennent du bulletin publie et ne sont pas recalcules depuis les notes en saisie. Les cours a faire sont limites a l etudiant et a l annee du bulletin. Le statut de l annee est determine depuis sa date de fin. Versions, QR et telechargement PDF ne sont pas geres par ces routes.',
    tags: ['Espace étudiant'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'etudiant et bulletin avec matieres, recapitulatif_uv (validees, non_validees, a_completer) et cours_a_faire.'),
        new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 403, description: 'Compte actif et role ETUDIANT requis.'),
        new OA\Response(response: 404, description: 'Bulletin inexistant, non publie ou appartenant a un autre etudiant.')]
)]
final class MesBulletinsEtudiantDocumentation {}
