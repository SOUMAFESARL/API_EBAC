<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'EntreeJournalAudit', required: ['id', 'horodatage', 'acteur', 'action', 'categorie', 'criticite', 'objet', 'valeurAvant', 'valeurApres', 'adresseIp'], properties: [
    new OA\Property(property: 'id', type: 'string', example: '1842'),
    new OA\Property(property: 'horodatage', description: 'ISO 8601 en temps universel.', type: 'string', format: 'date-time', example: '2026-11-09T08:14:03.271Z'),
    new OA\Property(property: 'acteur', required: ['id', 'nom', 'profil'], properties: [
        new OA\Property(property: 'id', description: 'null : action sans titulaire identifié, ou compte supprimé depuis.', type: 'string', nullable: true, example: '7'),
        new OA\Property(property: 'nom', description: 'Déjà formaté, conservé si le compte disparaît.', type: 'string', example: 'Dr. GBAGBO Samuel'),
        new OA\Property(property: 'profil', type: 'string', nullable: true, enum: ['administrateur', 'direction', 'gestionnaire', 'secretariat-academique', 'enseignant', 'etudiant', 'eglise']),
    ], type: 'object'),
    new OA\Property(property: 'action', description: 'Texte d affichage, pas un contrat.', type: 'string', example: 'Validation des notes par la Direction'),
    new OA\Property(property: 'categorie', type: 'string', enum: ['authentification', 'comptes', 'notes', 'corrections-notes', 'finances', 'documents-officiels', 'parametrage', 'exports', 'incidents']),
    new OA\Property(property: 'criticite', type: 'string', enum: ['moyen', 'eleve', 'critique']),
    new OA\Property(property: 'objet', required: ['type', 'libelle'], properties: [
        new OA\Property(property: 'type', type: 'string', example: 'Compte'),
        new OA\Property(property: 'libelle', type: 'string', example: 'm.diallo@ebac.ci'),
    ], type: 'object', nullable: true),
    new OA\Property(property: 'valeurAvant', description: 'Champ → valeur déjà mise en forme ; null si rien n était modifié.', type: 'object', nullable: true, additionalProperties: new OA\AdditionalProperties(type: 'string'), example: ['Statut' => 'Actif']),
    new OA\Property(property: 'valeurApres', type: 'object', nullable: true, additionalProperties: new OA\AdditionalProperties(type: 'string'), example: ['Statut' => 'Bloqué']),
    new OA\Property(property: 'adresseIp', type: 'string', nullable: true, example: '41.202.219.12'),
])]
#[OA\Schema(schema: 'RefusJournalAudit', required: ['code', 'message'], properties: [
    new OA\Property(property: 'code', type: 'string', enum: ['CATEGORIE_FERMEE']),
    new OA\Property(property: 'message', type: 'string', example: 'Cette catégorie du journal n’est pas ouverte à votre profil.'),
])]
#[OA\Tag(name: 'Journal d\'audit', description: 'Registre immuable des actions sensibles (CDC §15.4) : aucune route ne modifie ni ne supprime une entrée. Les écritures sensibles, connexions, refus d accès et erreurs serveur y sont inscrits par le service. ADMIN lit les neuf catégories ; tout autre titulaire du droit lit comptes, notes, corrections-notes, documents-officiels et parametrage.')]
#[OA\Get(path: '/administration/journal-audit', operationId: 'lireJournalAudit', summary: 'Une fenêtre du journal d audit', description: 'Droit : securite-comptes.journal-audit.consulter. Entrées antéchronologiques de la fenêtre (jour, semaine lundi→dimanche, ou mois civil) qui contient l ancre, bornées aux catégories lisibles du titulaire.', tags: ['Journal d\'audit'], security: [['sanctum' => []]], parameters: [
    new OA\Parameter(name: 'periode', in: 'query', required: true, schema: new OA\Schema(type: 'string', enum: ['jour', 'semaine', 'mois'])),
    new OA\Parameter(name: 'ancre', in: 'query', required: true, description: 'AAAA-MM-JJ, un jour de la fenêtre.', schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-11-02'),
    new OA\Parameter(name: 'categorie', in: 'query', required: false, description: 'Absent = toutes les catégories lisibles. Catégorie fermée au titulaire : 403 CATEGORIE_FERMEE.', schema: new OA\Schema(type: 'string', enum: ['authentification', 'comptes', 'notes', 'corrections-notes', 'finances', 'documents-officiels', 'parametrage', 'exports', 'incidents'])),
    new OA\Parameter(name: 'criticite_minimale', in: 'query', required: false, description: 'Retient les entrées au moins aussi graves.', schema: new OA\Schema(type: 'string', enum: ['moyen', 'eleve', 'critique'])),
    new OA\Parameter(name: 'recherche', in: 'query', required: false, description: 'Auteur, action, objet ou adresse IP.', schema: new OA\Schema(type: 'string', maxLength: 100)),
    new OA\Parameter(name: 'page', in: 'query', required: false, description: 'Ramenée à la dernière page si elle la dépasse.', schema: new OA\Schema(type: 'integer', minimum: 1, default: 1)),
    new OA\Parameter(name: 'par_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 10)),
], responses: [
    new OA\Response(response: 200, description: 'Page du registre', content: new OA\JsonContent(required: ['entrees', 'bornes', 'page', 'pages', 'total', 'parPage', 'categoriesLisibles'], properties: [
        new OA\Property(property: 'entrees', type: 'array', items: new OA\Items(ref: '#/components/schemas/EntreeJournalAudit')),
        new OA\Property(property: 'bornes', description: 'Fenêtre telle que le service l a comprise.', required: ['debut', 'fin'], properties: [
            new OA\Property(property: 'debut', type: 'string', format: 'date', example: '2026-11-02'),
            new OA\Property(property: 'fin', type: 'string', format: 'date', example: '2026-11-08'),
        ], type: 'object'),
        new OA\Property(property: 'page', type: 'integer', example: 1),
        new OA\Property(property: 'pages', description: 'Au moins 1.', type: 'integer', example: 3),
        new OA\Property(property: 'total', type: 'integer', example: 27),
        new OA\Property(property: 'parPage', type: 'integer', example: 10),
        new OA\Property(property: 'categoriesLisibles', type: 'array', items: new OA\Items(type: 'string'), example: ['comptes', 'notes', 'corrections-notes', 'documents-officiels', 'parametrage']),
    ])),
    new OA\Response(response: 401, description: 'Non authentifié'),
    new OA\Response(response: 403, description: 'Droit manquant, ou catégorie fermée (RefusJournalAudit)', content: new OA\JsonContent(ref: '#/components/schemas/RefusJournalAudit')),
    new OA\Response(response: 422, description: 'Période ou ancre inexploitable'),
])]
#[OA\Post(path: '/administration/journal-audit/exports', operationId: 'declarerExportJournalAudit', summary: 'Inscrire au journal un export CSV', description: 'Droit : securite-comptes.journal-audit.exporter. Le CSV est fabriqué par le client à partir des lignes affichées ; cet appel l inscrit au registre (catégorie exports). Seule écriture du module, et c est un ajout.', tags: ['Journal d\'audit'], security: [['sanctum' => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['periode', 'debut', 'fin', 'lignes'], properties: [
    new OA\Property(property: 'periode', type: 'string', enum: ['jour', 'semaine', 'mois']),
    new OA\Property(property: 'debut', type: 'string', format: 'date', example: '2026-11-02'),
    new OA\Property(property: 'fin', type: 'string', format: 'date', example: '2026-11-08'),
    new OA\Property(property: 'lignes', type: 'integer', minimum: 0, example: 27),
    new OA\Property(property: 'categorie', description: 'null = export non filtré.', type: 'string', nullable: true, enum: ['authentification', 'comptes', 'notes', 'corrections-notes', 'finances', 'documents-officiels', 'parametrage', 'exports', 'incidents']),
])), responses: [
    new OA\Response(response: 201, description: 'Export inscrit', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'id', type: 'string', nullable: true, example: '1843'),
    ])),
    new OA\Response(response: 401, description: 'Non authentifié'),
    new OA\Response(response: 403, description: 'Droit manquant, ou catégorie fermée', content: new OA\JsonContent(ref: '#/components/schemas/RefusJournalAudit')),
    new OA\Response(response: 422, description: 'Corps invalide'),
])]
final class JournalAuditDocumentation {}
