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
    description: 'Retourne les lignes enregistrees du bulletin, leur coefficient, note, appreciation et statut selon le seuil de validation. Les bulletins Généré sont synchronises lors des transitions du circuit de validation : validation secretariat ou direction, maintien des notes validees au secretariat pendant la transmission direction, retrait des notes rejetees ou retournees en correction. La publication reste distincte et fige le document. Les cours a faire sont limites a l etudiant et a l annee du bulletin. Le statut de l annee depend de sa date de fin. telechargement_pdf_url fournit le lien de telechargement authentifie. La mention est calculee si absente. qr_code contient verification_url et image_data_url (PNG base64). Le PDF inclut le QR. Le versionnement documentaire n est pas gere.',
    tags: ['Espace étudiant'], security: [['sanctum' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Profil personnel complet de l etudiant et bulletin avec matieres, recapitulatif_uv et cours_a_faire. Dates du profil au format YYYY-MM-DD. Relations absentes a null.',
        content: new OA\JsonContent(properties: [
            new OA\Property(property: 'etudiant', ref: '#/components/schemas/ProfilEtudiantBulletin'),
            new OA\Property(property: 'bulletin', type: 'object', description: 'Bulletin avec annee_academique, promotion, niveau, matieres (avec mention par matiere), moyenne_generale, rang, mention, recapitulatif_uv, cours_a_faire et telechargement_pdf_url.', properties: [
                new OA\Property(property: 'mention', description: 'Mention enregistree ou calculee : <10 Insuffisant, >=10 Passable, >=12 Assez bien, >=14 Bien, >=16 Tres bien. null sans moyenne.', type: 'string', nullable: true, example: 'Bien'),
                new OA\Property(property: 'qr_code', type: 'object', properties: [
                    new OA\Property(property: 'verification_url', description: 'URL signee permanente de verification. A utiliser integralement, sans token.', type: 'string'),
                    new OA\Property(property: 'image_data_url', description: 'Image PNG base64 affichable directement dans une balise img.', type: 'string', example: 'data:image/png;base64,...'),
                    new OA\Property(property: 'contenu', description: 'JSON directement encode dans le QR, lisible au scan. La decision provient du bulletin, sinon de decision_passage de l inscription. Site configure, sinon https://www.ebac-edu.org.', type: 'object', properties: [
                        new OA\Property(property: 'matricule', type: 'string', nullable: true),
                        new OA\Property(property: 'niveau', type: 'string', nullable: true),
                        new OA\Property(property: 'promotion', type: 'integer', nullable: true),
                        new OA\Property(property: 'decision_fin_annee', type: 'string', nullable: true),
                        new OA\Property(property: 'moyenne_generale', type: 'number', nullable: true),
                        new OA\Property(property: 'site_web', type: 'string'),
                        new OA\Property(property: 'verification_url', type: 'string'),
                    ]),
                ]),
            ]),
        ])),
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
#[OA\Get(path: '/bulletins/{id}/verification', operationId: 'verifierBulletinQrCode',
    summary: 'Vérifier un bulletin depuis son QR code', tags: ['Espace étudiant'],
    description: 'URL signee sans expiration et empreinte des resultats. Sans Bearer. Retourne uniquement les informations du bulletin, aucun profil personnel. Un bulletin Genere reste non publie. QR inclus dans le PDF et dans le detail JSON.',
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'empreinte', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'signature', in: 'query', required: true, schema: new OA\Schema(type: 'string'))],
    responses: [new OA\Response(response: 200, description: 'valide: true et bulletin (id, periode, statut, date_publication, annee_academique, moyenne_generale, mention).'),
        new OA\Response(response: 403, description: 'Signature absente ou invalide'),
        new OA\Response(response: 404, description: 'Bulletin introuvable ou retiré'),
        new OA\Response(response: 410, description: 'Résultats modifiés : télécharger le bulletin actuel'),
        new OA\Response(response: 429, description: 'Trop de requêtes')])]
#[OA\Schema(schema: 'ProfilEtudiantBulletin', type: 'object', properties: [
    new OA\Property(property: 'id', type: 'integer'),
    new OA\Property(property: 'matricule', type: 'string'),
    new OA\Property(property: 'nom', type: 'string'),
    new OA\Property(property: 'prenoms', type: 'string'),
    new OA\Property(property: 'civilite_id', type: 'integer', nullable: true),
    new OA\Property(property: 'civilite', type: 'string', nullable: true, example: 'Madame'),
    new OA\Property(property: 'date_naissance', type: 'string', format: 'date', nullable: true),
    new OA\Property(property: 'lieu_naissance', type: 'string', nullable: true),
    new OA\Property(property: 'nationalite', type: 'string', nullable: true),
    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
    new OA\Property(property: 'telephone', type: 'string', nullable: true),
    new OA\Property(property: 'adresse', type: 'string', nullable: true),
    new OA\Property(property: 'statut_professionnel', type: 'string', nullable: true),
    new OA\Property(property: 'situation_matrimonial', type: 'string', nullable: true),
    new OA\Property(property: 'nombre_enfant', type: 'integer', nullable: true),
    new OA\Property(property: 'statut', type: 'string'),
    new OA\Property(property: 'date_inscription', type: 'string', format: 'date', nullable: true),
    new OA\Property(property: 'photo_identite_url', description: 'URL signee temporaire de la photo.', type: 'string', nullable: true),
    new OA\Property(property: 'eglise_id', type: 'integer', nullable: true),
    new OA\Property(property: 'eglise', type: 'object', nullable: true, properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'code', type: 'string'),
        new OA\Property(property: 'nom', type: 'string'),
        new OA\Property(property: 'denomination', type: 'string', nullable: true),
        new OA\Property(property: 'region', type: 'string', nullable: true),
        new OA\Property(property: 'district', type: 'string', nullable: true),
        new OA\Property(property: 'ville_commune', type: 'string'),
    ]),
    new OA\Property(property: 'dossier', type: 'object', nullable: true, properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'numero_dossier', type: 'string'),
        new OA\Property(property: 'statut', type: 'string'),
        new OA\Property(property: 'date_ouverture', type: 'string', format: 'date', nullable: true),
    ]),
])]
final class MesBulletinsEtudiantDocumentation {}
