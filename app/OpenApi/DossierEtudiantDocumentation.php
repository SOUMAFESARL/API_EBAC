<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Dossier étudiant', description: 'Dossier du compte étudiant connecté. Utiliser Authorize avec son token Bearer. Aucun ID étudiant à envoyer. Les documents ajoutés ou remplacés sont en attente de validation.')]
#[OA\Schema(
    schema: 'ModifierDossierEtudiantPayload',
    type: 'object',
    description: 'Envoyer au moins un champ modifiable à la racine, sans enveloppe dossier. Les champs omis sont conservés. Le nom et les prénoms sont aussi synchronisés avec le compte.',
    properties: [
        new OA\Property(property: 'nom', type: 'string', minLength: 1, maxLength: 150, example: 'KOUAME'),
        new OA\Property(property: 'prenoms', type: 'string', minLength: 1, maxLength: 150, example: 'Anne Marie'),
        new OA\Property(property: 'civilite_id', type: 'integer', example: 1),
        new OA\Property(property: 'date_naissance', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'lieu_naissance', type: 'string', maxLength: 150, nullable: true),
        new OA\Property(property: 'nationalite', type: 'string', maxLength: 80, nullable: true),
        new OA\Property(property: 'telephone', type: 'string', maxLength: 30, nullable: true, example: '0102030405'),
        new OA\Property(property: 'adresse', type: 'string', maxLength: 255, nullable: true),
        new OA\Property(property: 'eglise_id', type: 'integer', nullable: true, description: 'ID d’une église existante non supprimée'),
        new OA\Property(property: 'statut_professionnel', type: 'string', maxLength: 100, nullable: true),
        new OA\Property(property: 'situation_matrimonial', type: 'string', maxLength: 50, nullable: true),
        new OA\Property(property: 'nombre_enfant', type: 'integer', minimum: 0, maximum: 65535, nullable: true),
    ],
)]
#[OA\Schema(
    schema: 'ModifierDossierEtudiantMultipart',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/ModifierDossierEtudiantPayload'),
        new OA\Schema(type: 'object', properties: [
            new OA\Property(property: 'photo_identite', type: 'string', format: 'binary', description: 'Fichier JPG, JPEG, PNG ou WEBP, 2 Mo maximum. Remplace aussi la photo de profil. Actualiser la photo affichée avec dossier.informations_personnelles.compte.photo_url dans la réponse.'),
            new OA\Property(property: 'documents[]', type: 'array', maxItems: 10, items: new OA\Items(type: 'string', format: 'binary'), description: 'Ajouter de nouveaux documents : fichiers PDF, JPG, JPEG, PNG ou WEBP, 10 Mo maximum chacun. Répéter la clé documents[] en multipart. Sélectionner les fichiers, ne pas envoyer leurs chemins. Pour remplacer un document, utiliser POST /etudiant/dossier/documents/{document}.'),
        ]),
    ],
)]
final class DossierEtudiantDocumentation {}
