<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'PersonneSupport', nullable: true, properties: [
    new OA\Property(property: 'id', type: 'integer', example: 5),
    new OA\Property(property: 'nom', type: 'string', example: 'KONAN'),
    new OA\Property(property: 'prenoms', type: 'string', nullable: true, example: 'Abraham'),
])]
#[OA\Schema(schema: 'SupportPedagogique', properties: [
    new OA\Property(property: 'id', type: 'integer', example: 12),
    new OA\Property(property: 'nom', type: 'string', example: 'Plan de cours'),
    new OA\Property(property: 'type', type: 'string', enum: ['cours', 'travaux-diriges', 'exercices', 'corrige', 'reference', 'media']),
    new OA\Property(property: 'versions', description: 'De la plus récente à la plus ancienne.', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'numero', type: 'integer', example: 2),
        new OA\Property(property: 'taille_mo', type: 'number', example: 2.4),
        new OA\Property(property: 'format', type: 'string', example: 'PDF'),
        new OA\Property(property: 'depose_le', type: 'string', format: 'date'),
        new OA\Property(property: 'note', type: 'string'),
        new OA\Property(property: 'depose_par', ref: '#/components/schemas/PersonneSupport'),
    ])),
])]
#[OA\Schema(schema: 'QuotaStockage', properties: [
    new OA\Property(property: 'enseignant_id', type: 'integer'),
    new OA\Property(property: 'enseignant', ref: '#/components/schemas/PersonneSupport'),
    new OA\Property(property: 'quota_mo', type: 'integer', example: 500),
    new OA\Property(property: 'utilise_mo', type: 'number', example: 42.3),
    new OA\Property(property: 'supports', type: 'integer'),
    new OA\Property(property: 'versions', type: 'integer'),
])]
#[OA\Schema(schema: 'EcritureSupport', properties: [
    new OA\Property(property: 'support', ref: '#/components/schemas/SupportPedagogique'),
    new OA\Property(property: 'quota', description: 'Quota du déposant enseignant ; null pour la gestion (hors quota).', ref: '#/components/schemas/QuotaStockage', nullable: true),
])]
#[OA\Tag(name: 'Bibliothèque pédagogique', description: 'Supports de cours versionnés : rien ne se supprime, une correction publie une version. Formats : PDF, DOCX, ODT, PPTX, ODP, XLSX, MP3, MP4, JPG, PNG, ZIP ; 50 Mo par fichier. Écritures : l enseignant (rôle ENSEIGNANT) sur ses cours affectés, dans son quota ; la gestion avec ressources.bibliotheque.creer (dépôt) ou ressources.bibliotheque.modifier (versions), hors quota. ADMIN passe toujours. Erreurs : { code, message } — COURS_NON_AFFECTE, SUPPORT_NON_AFFECTE, DROITS_INSUFFISANTS (403), SUPPORT_INTROUVABLE, VERSION_INTROUVABLE (404), FORMAT_REFUSE, FICHIER_TROP_LOURD, QUOTA_DEPASSE, QUOTA_INFERIEUR_UTILISE (409).')]
#[OA\Get(path: '/enseignant/bibliotheque', operationId: 'maBibliotheque', summary: 'Bibliothèque de l enseignant connecté', description: 'Rôle ENSEIGNANT. Cours affectés aujourd hui, rangés matière › module › cours, avec leurs supports et le quota.', tags: ['Bibliothèque pédagogique'], security: [['sanctum' => []]], responses: [
    new OA\Response(response: 200, description: 'enseignant, matieres[{id, code, libelle, niveau, modules[{id, libelle, cours[{id, libelle, supports[SupportPedagogique]}]}]}], quota (QuotaStockage)'),
    new OA\Response(response: 404, description: 'AUCUNE_AFFECTATION'),
])]
#[OA\Get(path: '/supports/bibliotheque', operationId: 'bibliothequeEtablissement', summary: 'Bibliothèque de l établissement', description: 'Droit requis : ressources.bibliotheque.consulter. ADMIN passe toujours. Documents rangés par domaine d enseignement puis matière ; id/code/libelle null pour les matières non classées.', tags: ['Bibliothèque pédagogique'], security: [['sanctum' => []]], responses: [
    new OA\Response(response: 200, description: 'domaines[{id, code, libelle, matieres[{id, code, libelle, niveau, enseignant_responsable, documents[{support, module, cours, ajoute_par}]}]}], matieres_sans_document'),
    new OA\Response(response: 403, description: 'Droit manquant'),
])]
#[OA\Post(path: '/supports', operationId: 'deposerSupport', summary: 'Déposer un support (v1)', tags: ['Bibliothèque pédagogique'], security: [['sanctum' => []]], requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(required: ['id_cours', 'nom', 'type', 'fichier'], properties: [
    new OA\Property(property: 'id_cours', type: 'integer'),
    new OA\Property(property: 'nom', type: 'string', minLength: 3, maxLength: 120),
    new OA\Property(property: 'type', type: 'string', enum: ['cours', 'travaux-diriges', 'exercices', 'corrige', 'reference', 'media']),
    new OA\Property(property: 'note', type: 'string', maxLength: 300, nullable: true),
    new OA\Property(property: 'fichier', type: 'string', format: 'binary'),
]))), responses: [
    new OA\Response(response: 201, description: 'Support créé', content: new OA\JsonContent(ref: '#/components/schemas/EcritureSupport')),
    new OA\Response(response: 403, description: 'COURS_NON_AFFECTE ou DROITS_INSUFFISANTS'),
    new OA\Response(response: 409, description: 'FORMAT_REFUSE, FICHIER_TROP_LOURD ou QUOTA_DEPASSE'),
    new OA\Response(response: 422, description: 'Validation invalide'),
])]
#[OA\Post(path: '/supports/{support}/versions', operationId: 'publierVersionSupport', summary: 'Publier une nouvelle version', tags: ['Bibliothèque pédagogique'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'support', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(required: ['note', 'fichier'], properties: [
    new OA\Property(property: 'note', type: 'string', minLength: 5, maxLength: 300),
    new OA\Property(property: 'fichier', type: 'string', format: 'binary'),
]))), responses: [
    new OA\Response(response: 200, description: 'Support avec son historique', content: new OA\JsonContent(ref: '#/components/schemas/EcritureSupport')),
    new OA\Response(response: 403, description: 'SUPPORT_NON_AFFECTE ou DROITS_INSUFFISANTS'),
    new OA\Response(response: 404, description: 'SUPPORT_INTROUVABLE'),
    new OA\Response(response: 409, description: 'FORMAT_REFUSE, FICHIER_TROP_LOURD ou QUOTA_DEPASSE'),
])]
#[OA\Post(path: '/supports/{support}/versions/{numero}/republication', operationId: 'republierVersionSupport', summary: 'Republier une version antérieure sous un numéro neuf', tags: ['Bibliothèque pédagogique'], security: [['sanctum' => []]], parameters: [
    new OA\Parameter(name: 'support', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    new OA\Parameter(name: 'numero', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
], responses: [
    new OA\Response(response: 200, description: 'Support avec son historique', content: new OA\JsonContent(ref: '#/components/schemas/EcritureSupport')),
    new OA\Response(response: 404, description: 'SUPPORT_INTROUVABLE ou VERSION_INTROUVABLE'),
    new OA\Response(response: 409, description: 'QUOTA_DEPASSE'),
    new OA\Response(response: 422, description: 'Version déjà courante'),
])]
#[OA\Get(path: '/supports/{support}/versions/{numero}/fichier', operationId: 'telechargerVersionSupport', summary: 'Télécharger une version', description: 'Enseignant du cours, étudiant d un niveau égal ou supérieur à celui de la matière, ou ressources.bibliotheque.consulter. ADMIN passe toujours.', tags: ['Bibliothèque pédagogique'], security: [['sanctum' => []]], parameters: [
    new OA\Parameter(name: 'support', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    new OA\Parameter(name: 'numero', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
], responses: [
    new OA\Response(response: 200, description: 'Contenu binaire du fichier'),
    new OA\Response(response: 403, description: 'DROITS_INSUFFISANTS'),
    new OA\Response(response: 404, description: 'VERSION_INTROUVABLE'),
])]
#[OA\Get(path: '/supports/quotas', operationId: 'listerQuotasSupports', summary: 'Quotas de stockage des enseignants', description: 'Droit requis : ressources.quotas-supports.consulter. ADMIN passe toujours.', tags: ['Bibliothèque pédagogique'], security: [['sanctum' => []]], responses: [
    new OA\Response(response: 200, description: 'quotas[QuotaStockage]'),
])]
#[OA\Put(path: '/supports/quotas/{enseignant}', operationId: 'definirQuotaSupports', summary: 'Fixer le quota d un enseignant', description: 'Droit requis : ressources.quotas-supports.modifier. ADMIN passe toujours. Le quota ne descend pas sous l espace occupé.', tags: ['Bibliothèque pédagogique'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'enseignant', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['quota_mo'], properties: [
    new OA\Property(property: 'quota_mo', type: 'integer', minimum: 50, maximum: 20000),
])), responses: [
    new OA\Response(response: 200, description: 'quota (QuotaStockage)'),
    new OA\Response(response: 404, description: 'ENSEIGNANT_INTROUVABLE'),
    new OA\Response(response: 409, description: 'QUOTA_INFERIEUR_UTILISE'),
])]
final class SupportPedagogiqueDocumentation {}
