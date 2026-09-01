<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'API EBAC',
    description: 'Documentation OpenAPI des API EBAC : églises, paramètres académiques et pré-inscription.',
)]
#[OA\Server(url: '/api/v1', description: 'Serveur courant')]
#[OA\Server(url: 'https://api-ebac.soumafe.com/api/v1', description: 'Production')]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Token Sanctum',
    description: 'Jeton retourné après la connexion et la confirmation OTP.',
)]
#[OA\Schema(
    schema: 'RepresentantEglise',
    type: 'object',
    required: ['nom'],
    properties: [
        new OA\Property(property: 'nom', type: 'string', maxLength: 150, example: 'Kouassi'),
        new OA\Property(property: 'prenoms', type: 'string', nullable: true, maxLength: 150, example: 'Jean'),
        new OA\Property(property: 'fonction', type: 'string', nullable: true, maxLength: 100, example: 'Secrétaire'),
        new OA\Property(property: 'telephone', type: 'string', nullable: true, maxLength: 30, example: '+2250102030405'),
        new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true, maxLength: 150, example: 'jean@example.com'),
    ],
)]
#[OA\Schema(
    schema: 'EglisePayload',
    type: 'object',
    required: ['nom', 'ville_commune'],
    properties: [
        new OA\Property(property: 'nom', type: 'string', maxLength: 180, example: 'Église Cité de la Grâce'),
        new OA\Property(property: 'sigle', type: 'string', nullable: true, maxLength: 30, example: 'ECG'),
        new OA\Property(property: 'pasteur_principal', type: 'string', nullable: true, maxLength: 180, example: 'Pasteur Yao Thomas'),
        new OA\Property(property: 'denomination', type: 'string', nullable: true, maxLength: 180, example: 'Église évangélique'),
        new OA\Property(property: 'adresse', type: 'string', nullable: true, maxLength: 255, example: 'Cocody Angré'),
        new OA\Property(property: 'region', type: 'string', nullable: true, maxLength: 120, example: 'District autonome d’Abidjan'),
        new OA\Property(property: 'district', type: 'string', nullable: true, maxLength: 120, example: 'Abidjan Nord'),
        new OA\Property(property: 'ville_commune', type: 'string', maxLength: 120, example: 'Cocody'),
        new OA\Property(property: 'telephone', type: 'string', nullable: true, maxLength: 30, example: '+2250102030405'),
        new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true, maxLength: 150, example: 'contact@eglise.example'),
        new OA\Property(property: 'statut', type: 'string', enum: ['Active', 'Suspendue', 'Archivée'], default: 'Active'),
        new OA\Property(property: 'capacite_max_stagiaires', type: 'integer', minimum: 0, maximum: 65535, default: 0, example: 25),
        new OA\Property(property: 'representants', type: 'array', nullable: true, items: new OA\Items(ref: '#/components/schemas/RepresentantEglise')),
        new OA\Property(property: 'observations', type: 'string', nullable: true, example: 'Église partenaire depuis 2026.'),
    ],
)]
#[OA\Schema(
    schema: 'EgliseModificationPayload',
    type: 'object',
    description: 'Champs modifiables d’une église. Tous les champs sont facultatifs pour une modification partielle.',
    properties: [
        new OA\Property(property: 'nom', type: 'string', maxLength: 180, example: 'Église Cité de la Grâce'),
        new OA\Property(property: 'sigle', type: 'string', nullable: true, maxLength: 30, example: 'ECG'),
        new OA\Property(property: 'pasteur_principal', type: 'string', nullable: true, maxLength: 180, example: 'Pasteur Yao Thomas'),
        new OA\Property(property: 'denomination', type: 'string', nullable: true, maxLength: 180, example: 'Église évangélique'),
        new OA\Property(property: 'adresse', type: 'string', nullable: true, maxLength: 255, example: 'Cocody Angré'),
        new OA\Property(property: 'region', type: 'string', nullable: true, maxLength: 120, example: 'District autonome d’Abidjan'),
        new OA\Property(property: 'district', type: 'string', nullable: true, maxLength: 120, example: 'Abidjan Nord'),
        new OA\Property(property: 'ville_commune', type: 'string', maxLength: 120, example: 'Cocody'),
        new OA\Property(property: 'telephone', type: 'string', nullable: true, maxLength: 30, example: '+2250102030405'),
        new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true, maxLength: 150, example: 'contact@eglise.example'),
        new OA\Property(property: 'statut', type: 'string', enum: ['Active', 'Suspendue', 'Archivée'], example: 'Active'),
        new OA\Property(property: 'capacite_max_stagiaires', type: 'integer', minimum: 0, maximum: 65535, example: 25),
        new OA\Property(property: 'representants', type: 'array', nullable: true, items: new OA\Items(ref: '#/components/schemas/RepresentantEglise')),
        new OA\Property(property: 'observations', type: 'string', nullable: true, example: 'Église partenaire depuis 2026.'),
    ],
)]
#[OA\Schema(
    schema: 'UtilisateurEglise',
    type: 'object',
    description: 'Informations succinctes d’un utilisateur lié à l’église.',
    properties: [
        new OA\Property(property: 'id', type: 'integer', readOnly: true, example: 1),
        new OA\Property(property: 'code', type: 'string', nullable: true, readOnly: true, example: 'USR-ADMIN'),
        new OA\Property(property: 'nom', type: 'string', readOnly: true, example: 'Kouassi'),
        new OA\Property(property: 'prenoms', type: 'string', nullable: true, readOnly: true, example: 'Jean'),
        new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true, readOnly: true, example: 'jean@example.com'),
    ],
)]
#[OA\Schema(
    schema: 'Eglise',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', readOnly: true, example: 1),
        new OA\Property(property: 'code', type: 'string', readOnly: true, example: 'EGL-000001'),
        new OA\Property(property: 'nom', type: 'string', example: 'Église Cité de la Grâce'),
        new OA\Property(property: 'sigle', type: 'string', nullable: true, example: 'ECG'),
        new OA\Property(property: 'pasteur_principal', type: 'string', nullable: true, example: 'Pasteur Yao Thomas'),
        new OA\Property(property: 'denomination', type: 'string', nullable: true),
        new OA\Property(property: 'adresse', type: 'string', nullable: true),
        new OA\Property(property: 'region', type: 'string', nullable: true),
        new OA\Property(property: 'district', type: 'string', nullable: true),
        new OA\Property(property: 'ville_commune', type: 'string', example: 'Cocody'),
        new OA\Property(property: 'telephone', type: 'string', nullable: true),
        new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
        new OA\Property(property: 'statut', type: 'string', enum: ['Active', 'Suspendue', 'Archivée']),
        new OA\Property(property: 'capacite_max_stagiaires', type: 'integer', example: 25),
        new OA\Property(property: 'representants', type: 'array', nullable: true, items: new OA\Items(ref: '#/components/schemas/RepresentantEglise')),
        new OA\Property(property: 'observations', type: 'string', nullable: true),
        new OA\Property(property: 'user_id', type: 'integer', readOnly: true, description: 'ID de l’utilisateur connecté ayant créé l’église', example: 1),
        new OA\Property(property: 'user_code', type: 'string', readOnly: true, description: 'Code de l’utilisateur connecté ayant créé l’église', example: 'USR-ADMIN'),
        new OA\Property(property: 'created_by', type: 'integer', nullable: true, readOnly: true),
        new OA\Property(property: 'updated_by', type: 'integer', nullable: true, readOnly: true),
        new OA\Property(property: 'deleted_by', type: 'integer', nullable: true, readOnly: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', readOnly: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', readOnly: true),
        new OA\Property(property: 'deleted_at', type: 'string', format: 'date-time', nullable: true, readOnly: true),
        new OA\Property(property: 'nombre_etudiants', type: 'integer', minimum: 0, readOnly: true, description: 'Nombre total d’étudiants rattachés à l’église.', example: 12),
        new OA\Property(property: 'compte', ref: '#/components/schemas/UtilisateurEglise', nullable: true, readOnly: true),
        new OA\Property(property: 'createur', ref: '#/components/schemas/UtilisateurEglise', nullable: true, readOnly: true),
        new OA\Property(property: 'modificateur', ref: '#/components/schemas/UtilisateurEglise', nullable: true, readOnly: true),
    ],
)]
#[OA\Schema(
    schema: 'StatistiquesEtudiantsEglise',
    type: 'object',
    required: ['total', 'avec_niveau', 'sans_niveau', 'par_niveau'],
    properties: [
        new OA\Property(property: 'total', type: 'integer', minimum: 0, example: 12),
        new OA\Property(property: 'avec_niveau', type: 'integer', minimum: 0, example: 10),
        new OA\Property(property: 'sans_niveau', type: 'integer', minimum: 0, example: 2),
        new OA\Property(property: 'par_niveau', type: 'array', items: new OA\Items(
            type: 'object',
            required: ['niveau_id', 'niveau_code', 'niveau_libelle', 'niveau_rang', 'nombre_etudiants'],
            properties: [
                new OA\Property(property: 'niveau_id', type: 'integer', example: 1),
                new OA\Property(property: 'niveau_code', type: 'string', example: 'A1'),
                new OA\Property(property: 'niveau_libelle', type: 'string', example: 'Première Année'),
                new OA\Property(property: 'niveau_rang', type: 'integer', example: 1),
                new OA\Property(property: 'nombre_etudiants', type: 'integer', minimum: 0, example: 6),
            ],
        )),
    ],
)]
#[OA\Schema(
    schema: 'EgliseDetail',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/Eglise'),
        new OA\Schema(type: 'object', properties: [
            new OA\Property(property: 'statistiques_etudiants', ref: '#/components/schemas/StatistiquesEtudiantsEglise', readOnly: true),
        ]),
    ],
)]
#[OA\Schema(
    schema: 'ErreurAuthentification',
    type: 'object',
    properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')],
)]
#[OA\Schema(
    schema: 'NiveauPayload',
    type: 'object',
    required: ['libelle', 'code', 'rang'],
    properties: [
        new OA\Property(property: 'libelle', type: 'string', maxLength: 100, example: 'Première Année'),
        new OA\Property(property: 'code', type: 'string', maxLength: 20, example: 'A1'),
        new OA\Property(property: 'rang', type: 'integer', minimum: 1, maximum: 65535, example: 1),
        new OA\Property(property: 'statut', type: 'string', enum: ['Actif', 'Archive'], default: 'Actif'),
    ],
)]
#[OA\Schema(
    schema: 'Niveau',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', readOnly: true, example: 1),
        new OA\Property(property: 'libelle', type: 'string', example: 'Première Année'),
        new OA\Property(property: 'code', type: 'string', example: 'A1'),
        new OA\Property(property: 'rang', type: 'integer', example: 1),
        new OA\Property(property: 'statut', type: 'string', enum: ['Actif', 'Archive'], example: 'Actif'),
        new OA\Property(property: 'user_id', type: 'integer', nullable: true, readOnly: true, example: 1),
        new OA\Property(property: 'user_code', type: 'string', nullable: true, readOnly: true, example: 'ADMIN'),
        new OA\Property(property: 'created_by', type: 'integer', nullable: true, readOnly: true, example: 1),
        new OA\Property(property: 'updated_by', type: 'integer', nullable: true, readOnly: true),
        new OA\Property(property: 'deleted_by', type: 'integer', nullable: true, readOnly: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', readOnly: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', readOnly: true),
        new OA\Property(property: 'deleted_at', type: 'string', format: 'date-time', nullable: true, readOnly: true),
    ],
)]
#[OA\Schema(
    schema: 'ErreurValidation',
    type: 'object',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'The nom field is required.'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(type: 'array', items: new OA\Items(type: 'string')),
        ),
    ],
)]
final class EgliseDocumentation
{
}
