<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'LigneRegistreBulletins', required: ['id', 'periode', 'fin_de_cycle', 'statut', 'moyenne', 'mention', 'date_publication', 'valide_par', 'valide_le', 'publie_par', 'etudiant', 'promotion', 'annee_academique', 'niveau'], properties: [
    new OA\Property(property: 'id', type: 'integer', example: 812),
    new OA\Property(property: 'periode', type: 'string', example: 'Fin de cycle'),
    new OA\Property(property: 'fin_de_cycle', description: 'Bulletin de fin de cycle (épreuves) plutôt qu annuel (matières).', type: 'boolean', example: true),
    new OA\Property(property: 'statut', type: 'string', enum: ['Brouillon', 'Généré', 'Validé', 'Publié'], example: 'Publié'),
    new OA\Property(property: 'moyenne', type: 'number', nullable: true, example: 14.46),
    new OA\Property(property: 'mention', type: 'string', nullable: true, example: 'Bien'),
    new OA\Property(property: 'date_publication', type: 'string', format: 'date-time', nullable: true),
    new OA\Property(property: 'valide_par', ref: '#/components/schemas/PersonneFinDeCycle', nullable: true),
    new OA\Property(property: 'valide_le', type: 'string', format: 'date-time', nullable: true),
    new OA\Property(property: 'publie_par', ref: '#/components/schemas/PersonneFinDeCycle', nullable: true),
    new OA\Property(property: 'etudiant', nullable: true, properties: [
        new OA\Property(property: 'id', type: 'integer'), new OA\Property(property: 'matricule', type: 'string', nullable: true),
        new OA\Property(property: 'nom', type: 'string'), new OA\Property(property: 'prenoms', type: 'string', nullable: true),
    ], type: 'object'),
    new OA\Property(property: 'promotion', nullable: true, properties: [
        new OA\Property(property: 'id', type: 'integer'), new OA\Property(property: 'code', type: 'string', nullable: true),
        new OA\Property(property: 'num_promotion', type: 'integer', nullable: true),
    ], type: 'object'),
    new OA\Property(property: 'annee_academique', nullable: true, properties: [
        new OA\Property(property: 'id', type: 'integer'), new OA\Property(property: 'libelle', type: 'string'),
    ], type: 'object'),
    new OA\Property(property: 'niveau', description: 'Niveau du bulletin à son émission, pas celui qu a aujourd hui la promotion.', nullable: true, properties: [
        new OA\Property(property: 'id', type: 'integer'), new OA\Property(property: 'code', type: 'string'),
        new OA\Property(property: 'libelle', type: 'string'), new OA\Property(property: 'rang', type: 'integer'),
    ], type: 'object'),
])]
#[OA\Tag(name: 'Registre des bulletins', description: 'Tous les bulletins d un niveau — annuels et de fin de cycle, quel que soit leur statut — pour l administration. Droit : notes.bulletins.consulter (ADMIN passe toujours).')]
#[OA\Get(path: '/bulletins/filtres', operationId: 'filtresRegistreBulletins', summary: 'Niveaux et années du registre', description: 'Droit : notes.bulletins.consulter. Les niveaux par rang, chacun avec son nombre de bulletins, et les années académiques de la plus récente à la plus ancienne.', tags: ['Registre des bulletins'], security: [['sanctum' => []]], responses: [
    new OA\Response(response: 200, description: 'Filtres', content: new OA\JsonContent(required: ['niveaux', 'annees_academiques'], properties: [
        new OA\Property(property: 'niveaux', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'id', type: 'integer'), new OA\Property(property: 'code', type: 'string'),
            new OA\Property(property: 'libelle', type: 'string'), new OA\Property(property: 'rang', type: 'integer'),
            new OA\Property(property: 'bulletins', type: 'integer', example: 42),
        ])),
        new OA\Property(property: 'annees_academiques', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'id', type: 'integer'), new OA\Property(property: 'libelle', type: 'string'),
            new OA\Property(property: 'date_debut', type: 'string', format: 'date'), new OA\Property(property: 'date_fin', type: 'string', format: 'date'),
        ])),
    ])),
    new OA\Response(response: 403, description: 'Droit manquant'),
])]
#[OA\Get(path: '/bulletins', operationId: 'listerRegistreBulletins', summary: 'Bulletins d un niveau', description: 'Droit : notes.bulletins.consulter. Tous les bulletins dont le niveau est donné, du plus récent au plus ancien ; restreints à une année si elle est donnée.', tags: ['Registre des bulletins'], security: [['sanctum' => []]], parameters: [
    new OA\Parameter(name: 'id_niveau', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
    new OA\Parameter(name: 'id_annee_academique', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
], responses: [
    new OA\Response(response: 200, description: 'Bulletins', content: new OA\JsonContent(required: ['bulletins'], properties: [
        new OA\Property(property: 'bulletins', type: 'array', items: new OA\Items(ref: '#/components/schemas/LigneRegistreBulletins')),
    ])),
    new OA\Response(response: 422, description: 'Niveau absent ou inconnu'),
])]
#[OA\Get(path: '/bulletins/{bulletin}', operationId: 'documentRegistreBulletins', summary: 'Document d un bulletin', description: 'Droit : notes.bulletins.consulter. La même forme que GET /etudiant/bulletins/{id} : { etudiant, bulletin } ; un bulletin de fin de cycle sert fin_de_cycle (elements, total, moyenne_generale, mention) à la place des matières.', tags: ['Registre des bulletins'], security: [['sanctum' => []]], parameters: [
    new OA\Parameter(name: 'bulletin', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
], responses: [
    new OA\Response(response: 200, description: 'Document', content: new OA\JsonContent(required: ['etudiant', 'bulletin'], properties: [
        new OA\Property(property: 'etudiant', type: 'object', nullable: true),
        new OA\Property(property: 'bulletin', type: 'object'),
    ])),
    new OA\Response(response: 404, description: 'BULLETIN_INTROUVABLE'),
])]
final class RegistreBulletinsDocumentation {}
