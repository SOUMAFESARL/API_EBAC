<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'PeriodeCalendrier', type: 'object', required: ['date_debut', 'date_fin'], properties: [
    new OA\Property(property: 'date_debut', type: 'string', format: 'date'),
    new OA\Property(property: 'date_fin', type: 'string', format: 'date'),
])]
#[OA\Schema(schema: 'PeriodeCalendrierLibellee', type: 'object', required: ['date_debut', 'date_fin'], properties: [
    new OA\Property(property: 'libelle', type: 'string', nullable: true, example: 'Examen écrit — Théologie'),
    new OA\Property(property: 'date_debut', type: 'string', format: 'date'),
    new OA\Property(property: 'date_fin', type: 'string', format: 'date'),
])]
#[OA\Schema(schema: 'CalendrierPayload', description: 'Calendrier complet : POST pour créer, PUT pour remplacer toutes les rubriques. Ajouter ou retirer des lignes dans les tableaux avant enregistrement. Au moins un module, sans chevauchement et dans les bornes de l’année. Examens dans leur module ; rattrapages après leur module et dans l’année. Jours fériés et congés dans l’année. Une seule période de grandes vacances, après les modules, congés et rattrapages ; elle peut dépasser la fin de l’année. Envoyer [] pour les listes vides et null sans grandes vacances. — IMPORTANT — Tous les libellés (modules, examens, rattrapages, jours_feries, conges, grandes_vacances) sont SAISIS PAR LE FRONTEND (utilisateur). Le backend ne génère AUCUN libellé par défaut ; il enregistre et restitue strictement ceux qui sont envoyés. Les libellés non renseignés (examens, rattrapages, grandes_vacances) peuvent être omis ou null.', type: 'object', example: CalendrierAcademiqueDocumentation::EXEMPLE, required: ['modules', 'jours_feries', 'conges', 'grandes_vacances'], properties: [
    new OA\Property(property: 'modules', type: 'array', minItems: 1, items: new OA\Items(type: 'object', required: ['libelle', 'date_debut', 'date_fin', 'examens', 'rattrapages'], properties: [
        new OA\Property(property: 'libelle', type: 'string', example: 'Théologie systématique — Semestre 1'),
        new OA\Property(property: 'date_debut', type: 'string', format: 'date'),
        new OA\Property(property: 'date_fin', type: 'string', format: 'date'),
        new OA\Property(property: 'examens', type: 'array', items: new OA\Items(ref: '#/components/schemas/PeriodeCalendrierLibellee')),
        new OA\Property(property: 'rattrapages', type: 'array', items: new OA\Items(ref: '#/components/schemas/PeriodeCalendrierLibellee')),
    ])),
    new OA\Property(property: 'jours_feries', type: 'array', items: new OA\Items(type: 'object', required: ['libelle', 'date'], properties: [
        new OA\Property(property: 'libelle', type: 'string', example: 'Fête de l\'indépendance'),
        new OA\Property(property: 'date', type: 'string', format: 'date'),
    ])),
    new OA\Property(property: 'conges', type: 'array', items: new OA\Items(type: 'object', required: ['libelle', 'date_debut', 'date_fin'], properties: [
        new OA\Property(property: 'libelle', type: 'string', example: 'Pause semestrielle'),
        new OA\Property(property: 'date_debut', type: 'string', format: 'date'),
        new OA\Property(property: 'date_fin', type: 'string', format: 'date'),
    ])),
    new OA\Property(property: 'grandes_vacances', type: 'object', nullable: true, required: ['date_debut', 'date_fin'], properties: [
        new OA\Property(property: 'libelle', type: 'string', nullable: true, example: 'Grandes vacances estivales'),
        new OA\Property(property: 'date_debut', type: 'string', format: 'date'),
        new OA\Property(property: 'date_fin', type: 'string', format: 'date'),
    ]),
])]
#[OA\Schema(schema: 'CalendrierAcademique', type: 'object', allOf: [new OA\Schema(ref: '#/components/schemas/CalendrierPayload')], properties: [
    new OA\Property(property: 'id', type: 'integer', readOnly: true, example: 1),
    new OA\Property(property: 'id_annee_academique', type: 'integer', readOnly: true, example: 1),
    new OA\Property(property: 'created_by', type: 'integer', nullable: true, readOnly: true),
    new OA\Property(property: 'updated_by', type: 'integer', nullable: true, readOnly: true),
    new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', readOnly: true),
])]
final class CalendrierAcademiqueDocumentation
{
    public const EXEMPLE = [
        'modules' => [
            ['libelle' => 'Théologie systématique — Semestre 1', 'date_debut' => '2026-09-01', 'date_fin' => '2026-12-20',
                'examens' => [['libelle' => 'Partiel — Théologie', 'date_debut' => '2026-12-15', 'date_fin' => '2026-12-20']],
                'rattrapages' => [['libelle' => 'Rattrapage janvier', 'date_debut' => '2027-01-05', 'date_fin' => '2027-01-10']]],
            ['libelle' => 'Bible hébraïque — Semestre 2', 'date_debut' => '2027-01-11', 'date_fin' => '2027-06-30',
                'examens' => [], 'rattrapages' => [['libelle' => null, 'date_debut' => '2027-07-01', 'date_fin' => '2027-07-10']]],
        ],
        'jours_feries' => [['libelle' => 'Fête de l\'indépendance', 'date' => '2026-11-01']],
        'conges' => [['libelle' => 'Pause semestrielle de Noël', 'date_debut' => '2026-12-21', 'date_fin' => '2027-01-04']],
        'grandes_vacances' => ['libelle' => 'Grandes vacances estivales', 'date_debut' => '2027-07-31', 'date_fin' => '2027-08-31'],
    ];
}
