<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/* --- Schémas ------------------------------------------------------------- */

#[OA\Schema(schema: 'EpreuveFinDeCycle', required: ['id', 'code', 'libelle', 'coefficient', 'rang', 'actif'], properties: [
    new OA\Property(property: 'id', type: 'integer', example: 2),
    new OA\Property(property: 'code', description: 'Attribué par le service à la création, ne change plus.', type: 'string', example: 'predication'),
    new OA\Property(property: 'libelle', type: 'string', example: 'Prédication'),
    new OA\Property(property: 'coefficient', type: 'integer', minimum: 1, maximum: 20, example: 1),
    new OA\Property(property: 'rang', description: 'Ordre d affichage sur le bulletin.', type: 'integer', example: 2),
    new OA\Property(property: 'actif', description: 'false = retirée : n entre plus dans la moyenne et ne reçoit plus de notes.', type: 'boolean', example: true),
])]
#[OA\Schema(schema: 'PromotionFinDeCycle', required: ['id', 'code', 'num_promotion', 'niveau', 'effectif'], properties: [
    new OA\Property(property: 'id', type: 'integer', example: 4),
    new OA\Property(property: 'code', type: 'string', example: 'PROMO-000013'),
    new OA\Property(property: 'num_promotion', type: 'integer', nullable: true, example: 13),
    new OA\Property(property: 'niveau', nullable: true, properties: [
        new OA\Property(property: 'id', type: 'integer', example: 3),
        new OA\Property(property: 'libelle', type: 'string', example: '3e Année'),
        new OA\Property(property: 'rang', type: 'integer', example: 3),
    ], type: 'object'),
    new OA\Property(property: 'effectif', description: 'Étudiants inscrits dans la promotion.', type: 'integer', example: 24),
])]
#[OA\Schema(schema: 'FeuilleFinDeCycle', required: ['promotion', 'epreuves', 'etudiants'], properties: [
    new OA\Property(property: 'promotion', ref: '#/components/schemas/PromotionFinDeCycle'),
    new OA\Property(property: 'epreuves', description: 'Épreuves actives, par rang.', type: 'array', items: new OA\Items(ref: '#/components/schemas/EpreuveFinDeCycle')),
    new OA\Property(property: 'etudiants', description: 'Étudiants inscrits dans la promotion, par nom.', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'id', type: 'integer', example: 51),
        new OA\Property(property: 'matricule', type: 'string', nullable: true, example: '411-2021'),
        new OA\Property(property: 'nom', type: 'string', example: 'KOUADIO'),
        new OA\Property(property: 'prenoms', type: 'string', nullable: true, example: 'Yao Devallois'),
        new OA\Property(property: 'notes', description: 'Notes portées ; une épreuve absente n est pas encore notée.', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'id_epreuve', type: 'integer', example: 2),
            new OA\Property(property: 'note', type: 'number', example: 13.17),
            new OA\Property(property: 'saisi_par', description: 'Compte qui a saisi la note (ou l a reprise en dernier).', nullable: true, properties: [
                new OA\Property(property: 'id', type: 'integer', example: 7),
                new OA\Property(property: 'nom', type: 'string', example: 'ADJOUA'),
                new OA\Property(property: 'prenoms', type: 'string', nullable: true, example: 'Marie-Claire'),
            ], type: 'object'),
            new OA\Property(property: 'saisi_le', type: 'string', format: 'date-time', nullable: true),
        ])),
    ])),
])]
#[OA\Schema(schema: 'RefusFinDeCycle', required: ['code', 'message'], properties: [
    new OA\Property(property: 'code', type: 'string', enum: ['EPREUVE_INTROUVABLE', 'PROMOTION_INTROUVABLE', 'REFERENTIEL_VIDE', 'EPREUVE_RETIREE', 'ETUDIANT_HORS_PROMOTION'], example: 'REFERENTIEL_VIDE'),
    new OA\Property(property: 'message', type: 'string', example: 'La dernière épreuve active ne peut pas être retirée.'),
])]

/* --- Opérations ---------------------------------------------------------- */

#[OA\Tag(name: 'Fin de cycle', description: 'Bulletin de fin de cycle (3e Année) : référentiel des épreuves et saisie de leurs notes. Une épreuve ne se supprime pas, elle se retire. Chaque note garde qui l a saisie. La moyenne de fin de cycle n est pas servie : moyenne pondérée des épreuves actives. ADMIN passe toujours. Erreurs : { code, message } (RefusFinDeCycle).')]
#[OA\Get(path: '/fin-de-cycle/epreuves', operationId: 'listerEpreuvesFinDeCycle', summary: 'Référentiel des épreuves de fin de cycle', description: 'Droit : notes.fin-de-cycle-epreuves.consulter ou notes.fin-de-cycle-notes.consulter. Toutes les épreuves, retirées comprises, par rang.', tags: ['Fin de cycle'], security: [['sanctum' => []]], responses: [
    new OA\Response(response: 200, description: 'Épreuves', content: new OA\JsonContent(required: ['epreuves'], properties: [
        new OA\Property(property: 'epreuves', type: 'array', items: new OA\Items(ref: '#/components/schemas/EpreuveFinDeCycle')),
    ])),
    new OA\Response(response: 403, description: 'Droit manquant'),
])]
#[OA\Post(path: '/fin-de-cycle/epreuves', operationId: 'creerEpreuveFinDeCycle', summary: 'Ajouter une épreuve', description: 'Droit : notes.fin-de-cycle-epreuves.creer. Le code est attribué par le service ; l épreuve prend le dernier rang.', tags: ['Fin de cycle'], security: [['sanctum' => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['libelle', 'coefficient'], properties: [
    new OA\Property(property: 'libelle', type: 'string', minLength: 3, maxLength: 120, example: 'Oral de synthèse'),
    new OA\Property(property: 'coefficient', type: 'integer', minimum: 1, maximum: 20, example: 1),
])), responses: [
    new OA\Response(response: 201, description: 'Épreuve créée', content: new OA\JsonContent(required: ['epreuve'], properties: [
        new OA\Property(property: 'epreuve', ref: '#/components/schemas/EpreuveFinDeCycle'),
    ])),
    new OA\Response(response: 422, description: 'Validation invalide'),
])]
#[OA\Patch(path: '/fin-de-cycle/epreuves/{epreuve}', operationId: 'modifierEpreuveFinDeCycle', summary: 'Reprendre une épreuve', description: 'Droit : notes.fin-de-cycle-epreuves.modifier. Intitulé et coefficient ; le code ne change pas.', tags: ['Fin de cycle'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'epreuve', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['libelle', 'coefficient'], properties: [
    new OA\Property(property: 'libelle', type: 'string', minLength: 3, maxLength: 120),
    new OA\Property(property: 'coefficient', type: 'integer', minimum: 1, maximum: 20),
])), responses: [
    new OA\Response(response: 200, description: 'Épreuve reprise', content: new OA\JsonContent(required: ['epreuve'], properties: [
        new OA\Property(property: 'epreuve', ref: '#/components/schemas/EpreuveFinDeCycle'),
    ])),
    new OA\Response(response: 404, description: 'EPREUVE_INTROUVABLE', content: new OA\JsonContent(ref: '#/components/schemas/RefusFinDeCycle')),
    new OA\Response(response: 422, description: 'Validation invalide'),
])]
#[OA\Post(path: '/fin-de-cycle/epreuves/{epreuve}/activation', operationId: 'activerEpreuveFinDeCycle', summary: 'Retirer une épreuve, ou la remettre', description: 'Droit : notes.fin-de-cycle-epreuves.retirer. Jamais de suppression ; la dernière épreuve active ne se retire pas.', tags: ['Fin de cycle'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'epreuve', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['actif'], properties: [
    new OA\Property(property: 'actif', type: 'boolean', example: false),
])), responses: [
    new OA\Response(response: 200, description: 'État enregistré', content: new OA\JsonContent(required: ['epreuve'], properties: [
        new OA\Property(property: 'epreuve', ref: '#/components/schemas/EpreuveFinDeCycle'),
    ])),
    new OA\Response(response: 404, description: 'EPREUVE_INTROUVABLE', content: new OA\JsonContent(ref: '#/components/schemas/RefusFinDeCycle')),
    new OA\Response(response: 409, description: 'REFERENTIEL_VIDE', content: new OA\JsonContent(ref: '#/components/schemas/RefusFinDeCycle')),
])]
#[OA\Get(path: '/fin-de-cycle/promotions', operationId: 'listerPromotionsFinDeCycle', summary: 'Promotions proposées à la saisie', description: 'Droit : notes.fin-de-cycle-notes.consulter. Du niveau le plus avancé au premier, puis de la promotion la plus récente.', tags: ['Fin de cycle'], security: [['sanctum' => []]], responses: [
    new OA\Response(response: 200, description: 'Promotions', content: new OA\JsonContent(required: ['promotions'], properties: [
        new OA\Property(property: 'promotions', type: 'array', items: new OA\Items(ref: '#/components/schemas/PromotionFinDeCycle')),
    ])),
    new OA\Response(response: 403, description: 'Droit manquant'),
])]
#[OA\Get(path: '/fin-de-cycle/promotions/{promotion}/notes', operationId: 'notesFinDeCycle', summary: 'Notes de fin de cycle d une promotion', description: 'Droit : notes.fin-de-cycle-notes.consulter.', tags: ['Fin de cycle'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'promotion', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [
    new OA\Response(response: 200, description: 'Feuille de la promotion', content: new OA\JsonContent(ref: '#/components/schemas/FeuilleFinDeCycle')),
    new OA\Response(response: 404, description: 'PROMOTION_INTROUVABLE', content: new OA\JsonContent(ref: '#/components/schemas/RefusFinDeCycle')),
])]
#[OA\Put(path: '/fin-de-cycle/promotions/{promotion}/epreuves/{epreuve}/notes', operationId: 'saisirNotesFinDeCycle', summary: 'Saisir les notes d une épreuve', description: 'Droit : notes.fin-de-cycle-notes.saisir. Une ligne à null ne touche rien (on n efface pas une note) ; une note déjà portée est reprise et prend le nom de qui la reprend. Renvoie la feuille à jour.', tags: ['Fin de cycle'], security: [['sanctum' => []]], parameters: [
    new OA\Parameter(name: 'promotion', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    new OA\Parameter(name: 'epreuve', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['notes'], properties: [
    new OA\Property(property: 'notes', type: 'array', minItems: 1, items: new OA\Items(required: ['id_etudiant'], properties: [
        new OA\Property(property: 'id_etudiant', type: 'integer', example: 51),
        new OA\Property(property: 'note', description: '0 à 20, deux décimales au plus ; null = non saisie.', type: 'number', nullable: true, example: 13.17),
    ])),
])), responses: [
    new OA\Response(response: 200, description: 'Feuille à jour', content: new OA\JsonContent(ref: '#/components/schemas/FeuilleFinDeCycle')),
    new OA\Response(response: 404, description: 'PROMOTION_INTROUVABLE ou EPREUVE_INTROUVABLE', content: new OA\JsonContent(ref: '#/components/schemas/RefusFinDeCycle')),
    new OA\Response(response: 409, description: 'EPREUVE_RETIREE', content: new OA\JsonContent(ref: '#/components/schemas/RefusFinDeCycle')),
    new OA\Response(response: 422, description: 'ETUDIANT_HORS_PROMOTION, ou note hors barème'),
])]
final class FinDeCycleDocumentation {}
