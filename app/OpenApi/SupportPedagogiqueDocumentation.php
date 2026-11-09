<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/* --- Schémas ------------------------------------------------------------- */

#[OA\Schema(schema: 'PersonneSupport', nullable: true, properties: [
    new OA\Property(property: 'id', type: 'integer', example: 5),
    new OA\Property(property: 'nom', type: 'string', example: 'KONAN'),
    new OA\Property(property: 'prenoms', type: 'string', nullable: true, example: 'Abraham'),
])]
#[OA\Schema(schema: 'SupportPedagogique', description: 'Un support = un fichier. Pas de versions : pour le remplacer, on le supprime (DELETE /supports/{support}) puis on dépose le nouveau.', required: ['id', 'nom', 'type', 'taille_mo', 'format', 'depose_le', 'depose_par'], properties: [
    new OA\Property(property: 'id', type: 'integer', example: 12),
    new OA\Property(property: 'nom', type: 'string', example: 'Plan de cours'),
    new OA\Property(property: 'type', type: 'string', enum: ['cours', 'travaux-diriges', 'exercices', 'corrige', 'reference', 'media'], example: 'cours'),
    new OA\Property(property: 'taille_mo', description: 'Taille en Mo, arrondie au dixième (0.1 minimum).', type: 'number', example: 2.4),
    new OA\Property(property: 'format', description: 'Extension du fichier en majuscules.', type: 'string', enum: ['PDF', 'DOCX', 'ODT', 'PPTX', 'ODP', 'XLSX', 'MP3', 'MP4', 'JPG', 'PNG', 'ZIP'], example: 'PDF'),
    new OA\Property(property: 'depose_le', type: 'string', format: 'date', nullable: true, example: '2026-11-09'),
    new OA\Property(property: 'depose_par', ref: '#/components/schemas/PersonneSupport'),
])]
#[OA\Schema(schema: 'QuotaStockage', description: 'Espace d un enseignant. utilise_mo = somme des fichiers qu il a déposés ; supprimer un support libère sa place.', required: ['enseignant_id', 'enseignant', 'quota_mo', 'utilise_mo', 'supports'], properties: [
    new OA\Property(property: 'enseignant_id', type: 'integer', example: 5),
    new OA\Property(property: 'enseignant', ref: '#/components/schemas/PersonneSupport'),
    new OA\Property(property: 'quota_mo', description: 'Espace alloué par l administration (500 Mo par défaut).', type: 'integer', example: 500),
    new OA\Property(property: 'utilise_mo', type: 'number', example: 42.3),
    new OA\Property(property: 'supports', description: 'Nombre de supports déposés par l enseignant.', type: 'integer', example: 7),
])]
#[OA\Schema(schema: 'EcritureSupport', required: ['support', 'quota'], properties: [
    new OA\Property(property: 'support', ref: '#/components/schemas/SupportPedagogique'),
    new OA\Property(property: 'quota', description: 'Quota du déposant enseignant ; null pour la gestion (hors quota).', ref: '#/components/schemas/QuotaStockage', nullable: true),
])]
#[OA\Schema(schema: 'SuppressionSupport', required: ['quota'], properties: [
    new OA\Property(property: 'quota', description: 'Quota de l enseignant qui supprime, après libération ; null pour la gestion.', ref: '#/components/schemas/QuotaStockage', nullable: true),
])]
#[OA\Schema(schema: 'RefusSupport', required: ['code', 'message'], properties: [
    new OA\Property(property: 'code', type: 'string', enum: ['AUCUNE_AFFECTATION', 'COURS_NON_AFFECTE', 'SUPPORT_NON_AFFECTE', 'DROITS_INSUFFISANTS', 'SUPPORT_INTROUVABLE', 'ENSEIGNANT_INTROUVABLE', 'FORMAT_REFUSE', 'FICHIER_TROP_LOURD', 'QUOTA_DEPASSE', 'QUOTA_INFERIEUR_UTILISE'], example: 'QUOTA_DEPASSE'),
    new OA\Property(property: 'message', type: 'string', example: 'Espace de stockage insuffisant.'),
])]
#[OA\Schema(schema: 'BibliothequeEnseignant', required: ['enseignant', 'matieres', 'quota'], properties: [
    new OA\Property(property: 'enseignant', ref: '#/components/schemas/PersonneSupport'),
    new OA\Property(property: 'matieres', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'id', type: 'integer', example: 3),
        new OA\Property(property: 'code', type: 'string', example: 'MAT-1'),
        new OA\Property(property: 'libelle', type: 'string', example: 'Herméneutique'),
        new OA\Property(property: 'niveau', type: 'string', nullable: true, example: '1ère Année'),
        new OA\Property(property: 'modules', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'id', type: 'integer', example: 8),
            new OA\Property(property: 'libelle', type: 'string', example: 'Méthodes'),
            new OA\Property(property: 'cours', type: 'array', items: new OA\Items(properties: [
                new OA\Property(property: 'id', type: 'integer', example: 21),
                new OA\Property(property: 'libelle', type: 'string', example: 'Lecture'),
                new OA\Property(property: 'supports', type: 'array', items: new OA\Items(ref: '#/components/schemas/SupportPedagogique')),
            ])),
        ])),
    ])),
    new OA\Property(property: 'quota', ref: '#/components/schemas/QuotaStockage'),
])]
#[OA\Schema(schema: 'BibliothequeEtablissement', required: ['domaines', 'matieres_sans_document'], properties: [
    new OA\Property(property: 'domaines', description: 'Domaines d enseignement ayant au moins un document, par libellé ; les matières non classées viennent en dernier (id, code, libelle null).', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'id', type: 'integer', nullable: true, example: 2),
        new OA\Property(property: 'code', type: 'string', nullable: true, example: 'BIB'),
        new OA\Property(property: 'libelle', type: 'string', nullable: true, example: 'Connaissance de la Bible'),
        new OA\Property(property: 'matieres', description: 'Triées par rang de niveau puis libellé.', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'id', type: 'integer', example: 3),
            new OA\Property(property: 'code', type: 'string', example: 'MAT-1'),
            new OA\Property(property: 'libelle', type: 'string', example: 'Herméneutique'),
            new OA\Property(property: 'niveau', type: 'string', nullable: true, example: '1ère Année'),
            new OA\Property(property: 'enseignant_responsable', ref: '#/components/schemas/PersonneSupport'),
            new OA\Property(property: 'documents', type: 'array', items: new OA\Items(properties: [
                new OA\Property(property: 'support', ref: '#/components/schemas/SupportPedagogique'),
                new OA\Property(property: 'module', type: 'string', example: 'Méthodes'),
                new OA\Property(property: 'cours', type: 'string', example: 'Lecture'),
                new OA\Property(property: 'ajoute_par', ref: '#/components/schemas/PersonneSupport'),
            ])),
        ])),
    ])),
    new OA\Property(property: 'matieres_sans_document', description: 'Matières du programme sans aucun document, absentes de la liste.', type: 'integer', example: 14),
])]

/* --- Opérations ---------------------------------------------------------- */

#[OA\Tag(name: 'Bibliothèque pédagogique', description: 'Supports de cours. Un document = un fichier, sans versions : pour le remplacer, on le supprime puis on dépose le nouveau. Formats : PDF, DOCX, ODT, PPTX, ODP, XLSX, MP3, MP4, JPG, PNG, ZIP ; 50 Mo par fichier. Écritures : l enseignant (rôle ENSEIGNANT) sur ses cours affectés, dans son quota ; la gestion avec ressources.bibliotheque.creer (dépôt) ou ressources.bibliotheque.supprimer (suppression), hors quota. ADMIN passe toujours. Erreurs : { code, message } (RefusSupport).')]
#[OA\Get(path: '/enseignant/bibliotheque', operationId: 'maBibliotheque', summary: 'Bibliothèque de l enseignant connecté', description: 'Rôle ENSEIGNANT. Cours affectés aujourd hui (affectations par cours ou par matière entière), rangés matière › module › cours, avec leurs supports et le quota.', tags: ['Bibliothèque pédagogique'], security: [['sanctum' => []]], responses: [
    new OA\Response(response: 200, description: 'Bibliothèque de l enseignant', content: new OA\JsonContent(ref: '#/components/schemas/BibliothequeEnseignant')),
    new OA\Response(response: 404, description: 'AUCUNE_AFFECTATION', content: new OA\JsonContent(ref: '#/components/schemas/RefusSupport')),
])]
#[OA\Get(path: '/supports/bibliotheque', operationId: 'bibliothequeEtablissement', summary: 'Bibliothèque de l établissement', description: 'Droit requis : ressources.bibliotheque.consulter. ADMIN passe toujours. Tous les documents, rangés par domaine d enseignement puis matière.', tags: ['Bibliothèque pédagogique'], security: [['sanctum' => []]], responses: [
    new OA\Response(response: 200, description: 'Bibliothèque de l établissement', content: new OA\JsonContent(ref: '#/components/schemas/BibliothequeEtablissement')),
    new OA\Response(response: 403, description: 'Droit manquant'),
])]
#[OA\Post(path: '/supports', operationId: 'deposerSupport', summary: 'Déposer un support', description: 'Enseignant sur un cours qui lui est affecté (dans son quota), ou ressources.bibliotheque.creer sur n importe quel cours (hors quota). ADMIN passe toujours.', tags: ['Bibliothèque pédagogique'], security: [['sanctum' => []]], requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(required: ['id_cours', 'nom', 'type', 'fichier'], properties: [
    new OA\Property(property: 'id_cours', type: 'integer', example: 21),
    new OA\Property(property: 'nom', type: 'string', minLength: 3, maxLength: 120, example: 'Plan de cours'),
    new OA\Property(property: 'type', type: 'string', enum: ['cours', 'travaux-diriges', 'exercices', 'corrige', 'reference', 'media']),
    new OA\Property(property: 'fichier', description: '50 Mo maximum ; formats PDF, DOCX, ODT, PPTX, ODP, XLSX, MP3, MP4, JPG, PNG, ZIP.', type: 'string', format: 'binary'),
]))), responses: [
    new OA\Response(response: 201, description: 'Support créé', content: new OA\JsonContent(ref: '#/components/schemas/EcritureSupport')),
    new OA\Response(response: 403, description: 'COURS_NON_AFFECTE ou DROITS_INSUFFISANTS', content: new OA\JsonContent(ref: '#/components/schemas/RefusSupport')),
    new OA\Response(response: 409, description: 'FORMAT_REFUSE, FICHIER_TROP_LOURD ou QUOTA_DEPASSE', content: new OA\JsonContent(ref: '#/components/schemas/RefusSupport')),
    new OA\Response(response: 422, description: 'Validation invalide (champ manquant, cours inexistant, type hors liste)'),
])]
#[OA\Delete(path: '/supports/{support}', operationId: 'supprimerSupport', summary: 'Supprimer un support et son fichier', description: 'Définitif : la ligne et le fichier sont effacés, l espace du déposant est libéré. C est ainsi qu on remplace un document (supprimer puis redéposer). Enseignant du cours affecté, ou ressources.bibliotheque.supprimer. ADMIN passe toujours.', tags: ['Bibliothèque pédagogique'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'support', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [
    new OA\Response(response: 200, description: 'Support supprimé', content: new OA\JsonContent(ref: '#/components/schemas/SuppressionSupport')),
    new OA\Response(response: 403, description: 'SUPPORT_NON_AFFECTE ou DROITS_INSUFFISANTS', content: new OA\JsonContent(ref: '#/components/schemas/RefusSupport')),
    new OA\Response(response: 404, description: 'SUPPORT_INTROUVABLE', content: new OA\JsonContent(ref: '#/components/schemas/RefusSupport')),
])]
#[OA\Get(path: '/supports/{support}/fichier', operationId: 'telechargerSupport', summary: 'Télécharger un support', description: 'Enseignant du cours, étudiant d un niveau égal ou supérieur à celui de la matière, ou ressources.bibliotheque.consulter. ADMIN passe toujours. Le fichier est servi en pièce jointe sous son nom d origine.', tags: ['Bibliothèque pédagogique'], security: [['sanctum' => []]], parameters: [
    new OA\Parameter(name: 'support', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
], responses: [
    new OA\Response(response: 200, description: 'Contenu binaire du fichier (Content-Disposition: attachment)', content: new OA\MediaType(mediaType: 'application/octet-stream', schema: new OA\Schema(type: 'string', format: 'binary'))),
    new OA\Response(response: 403, description: 'DROITS_INSUFFISANTS', content: new OA\JsonContent(ref: '#/components/schemas/RefusSupport')),
    new OA\Response(response: 404, description: 'SUPPORT_INTROUVABLE', content: new OA\JsonContent(ref: '#/components/schemas/RefusSupport')),
])]
#[OA\Get(path: '/supports/quotas', operationId: 'listerQuotasSupports', summary: 'Quotas de stockage des enseignants', description: 'Droit requis : ressources.quotas-supports.consulter. ADMIN passe toujours. Tous les comptes ENSEIGNANT, par nom.', tags: ['Bibliothèque pédagogique'], security: [['sanctum' => []]], responses: [
    new OA\Response(response: 200, description: 'Quotas', content: new OA\JsonContent(required: ['quotas'], properties: [
        new OA\Property(property: 'quotas', type: 'array', items: new OA\Items(ref: '#/components/schemas/QuotaStockage')),
    ])),
    new OA\Response(response: 403, description: 'Droit manquant'),
])]
#[OA\Put(path: '/supports/quotas/{enseignant}', operationId: 'definirQuotaSupports', summary: 'Fixer le quota d un enseignant', description: 'Droit requis : ressources.quotas-supports.modifier. ADMIN passe toujours. Le quota ne descend pas sous l espace occupé.', tags: ['Bibliothèque pédagogique'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'enseignant', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['quota_mo'], properties: [
    new OA\Property(property: 'quota_mo', type: 'integer', minimum: 50, maximum: 20000, example: 800),
])), responses: [
    new OA\Response(response: 200, description: 'Quota fixé', content: new OA\JsonContent(required: ['quota'], properties: [
        new OA\Property(property: 'quota', ref: '#/components/schemas/QuotaStockage'),
    ])),
    new OA\Response(response: 404, description: 'ENSEIGNANT_INTROUVABLE', content: new OA\JsonContent(ref: '#/components/schemas/RefusSupport')),
    new OA\Response(response: 409, description: 'QUOTA_INFERIEUR_UTILISE', content: new OA\JsonContent(ref: '#/components/schemas/RefusSupport')),
    new OA\Response(response: 422, description: 'quota_mo hors bornes (50 à 20 000)'),
])]
final class SupportPedagogiqueDocumentation {}
