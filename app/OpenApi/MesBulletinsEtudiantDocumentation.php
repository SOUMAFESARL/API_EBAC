<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Get(path: '/etudiant/bulletins', operationId: 'listerMesBulletins',
    summary: 'Lister mes bulletins generes ou publies',
    description: 'Authentification Sanctum, compte actif et role ETUDIANT requis. Les bulletins Généré sont consultables sans publication. Les bulletins Publié restent visibles avec date_publication. Tri par annee academique decroissante. Aucun identifiant etudiant accepte. Les brouillons restent masques.',
    tags: ['Espace étudiant'], security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'id_annee_academique', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100)),
    ],
    responses: [new OA\Response(response: 200, description: 'etudiant, bulletins avec annee/promotion/niveau/moyenne/rang/mention et meta de pagination. Liste vide si aucun bulletin genere ou publie.'),
        new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 403, description: 'Compte actif et role ETUDIANT requis.'),
        new OA\Response(response: 404, description: 'Fiche etudiant introuvable.'),
        new OA\Response(response: 422, description: 'Filtres invalides.')]
)]
#[OA\Get(path: '/etudiant/bulletins/{id}', operationId: 'afficherMonBulletin',
    summary: 'Afficher mon bulletin genere ou publie',
    description: 'Retourne les lignes enregistrees du bulletin, leur coefficient, note, appreciation et statut selon le seuil de validation. Les bulletins Généré sont synchronises lors des transitions du circuit de validation : validation secretariat ou direction, maintien des notes validees au secretariat pendant la transmission direction, retrait des notes rejetees ou retournees en correction. La publication reste distincte et fige le document. Les cours a faire sont limites a l etudiant et a l annee du bulletin. Le statut de l annee depend de sa date de fin. telechargement_pdf_url fournit le lien de telechargement authentifie. Versions et QR ne sont pas geres.',
    tags: ['Espace étudiant'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'etudiant et bulletin avec matieres, recapitulatif_uv (validees, non_validees, a_completer) et cours_a_faire.'),
        new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 403, description: 'Compte actif et role ETUDIANT requis.'),
        new OA\Response(response: 404, description: 'Bulletin inexistant, brouillon, retire ou appartenant a un autre etudiant.')]
)]
#[OA\Get(path: '/etudiant/bulletins/{id}/telecharger', operationId: 'telechargerMonBulletinPdf',
    summary: 'Telecharger mon bulletin en PDF',
    description: 'Compte actif et role ETUDIANT requis. Memes droits que le detail : bulletin Généré sans publication ou Publié avec date_publication, appartenant a l etudiant connecte. PDF A4 avec photo integree depuis le stockage, etat civil, naissance, nationalite, coordonnees, situation familiale et professionnelle, inscription, dossier et eglise de rattachement, puis matieres, notes, coefficients, moyenne et recapitulatif UV. Les informations absentes sont affichees avec un tiret. Les bulletins generes portent une indication non publie. Reponse binaire avec Content-Disposition attachment et Cache-Control private, no-store.',
    tags: ['Espace étudiant'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Fichier bulletin-{id}.pdf',
        content: new OA\MediaType(mediaType: 'application/pdf', schema: new OA\Schema(type: 'string', format: 'binary'))),
        new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 403, description: 'Compte actif et role ETUDIANT requis.'),
        new OA\Response(response: 404, description: 'Fiche etudiant ou bulletin introuvable, bulletin masque ou appartenant a un autre etudiant.')]
)]
final class MesBulletinsEtudiantDocumentation {}
