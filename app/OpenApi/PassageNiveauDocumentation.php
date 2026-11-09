<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Get(path: '/administration/passages-niveau', operationId: 'listerPromotionsPassageNiveau', summary: 'Promotions éligibles au passage de niveau',
    description: 'Un de ces droits requis : progression.passage-de-niveau.consulter ou .executer. ADMIN passe toujours. Promotions en cours ayant un niveau suivant actif : la dernière année du cursus (4e Année) n’est jamais proposée. Effectif = inscriptions de l’année que la promotion achève.',
    tags: ['Passage de niveau'], security: [['sanctum' => []]],
    responses: [new OA\Response(response: 200, description: 'promotions[] : id, code, num_promotion, annee_entree, niveau_actuel, niveau_suivant, effectif.'),
        new OA\Response(response: 403, description: 'Droit manquant.')])]
#[OA\Get(path: '/administration/passages-niveau/{promotion}', operationId: 'rapportPassageNiveau', summary: 'Rapport d’état préalable au passage',
    description: 'Jamais mis en cache. genere_le (ms epoch) repart dans rapport_vu_le. notes_de_l_annee : matières actives du niveau, validee si toutes leurs feuilles sont validee_direction. etudiants : inscrits de l’année, matières validées / partielles / non commencées (présence validée et cours à faire), cours_a_faire, suit_la_promotion (faux pour un départ). obstacles : notes-en-attente (avertissement), dernier-niveau, promotion-close, promotion-vide, annee-suivante-absente.',
    tags: ['Passage de niveau'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'promotion', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Rapport d’avancement.'), new OA\Response(response: 403, description: 'Droit manquant.'),
        new OA\Response(response: 404, description: 'Promotion introuvable.')])]
#[OA\Post(path: '/administration/passages-niveau/{promotion}', operationId: 'executerPassageNiveau', summary: 'Faire passer toute la promotion au niveau suivant',
    description: 'Droit requis : progression.passage-de-niveau.executer. Collectif et irréversible. La promotion prend le niveau suivant ; chaque étudiant qui suit la formation reçoit une inscription dans l’année académique suivante (décision posée sur l’inscription quittée, parcours académique tenu). Les cours à faire restent attachés aux dossiers. Refus 409 avec code : DERNIER_NIVEAU, PROMOTION_CLOSE, ANNEE_SUIVANTE_ABSENTE, RAPPORT_PERIME, NOTES_EN_ATTENTE, PASSAGE_DEJA_EFFECTUE ; 404 PROMOTION_INTROUVABLE.',
    tags: ['Passage de niveau'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'promotion', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object', required: ['rapport_vu_le'], properties: [
        new OA\Property(property: 'rapport_vu_le', type: 'integer', example: 1793000000000),
        new OA\Property(property: 'malgre_notes_en_attente', type: 'boolean', example: false),
    ])),
    responses: [new OA\Response(response: 200, description: 'message et bilan : niveau_atteint_id, niveau_atteint_libelle, annee_academique, etudiants_avances, etudiants_non_avances, cours_a_faire_reportes.'),
        new OA\Response(response: 403, description: 'Droit manquant.'), new OA\Response(response: 404, description: 'PROMOTION_INTROUVABLE'),
        new OA\Response(response: 409, description: 'Refus fonctionnel (champ code).'), new OA\Response(response: 422, description: 'rapport_vu_le manquant.')])]
final class PassageNiveauDocumentation {}
