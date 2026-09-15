# Affectations enseignants

Une affectation relie un enseignant à une matière entière ou à un cours. Elle est indépendante de l’année académique.

## Depuis une matière

Lors de la création ou modification d’une matière, `enseignant_id` crée automatiquement une affectation de portée `matiere`, avec une prise d’effet à la date du jour. Aucune année n’est nécessaire.

- Envoyer le même enseignant conserve l’affectation sans doublon.
- Changer d’enseignant termine l’ancienne affectation à la date du jour et crée la nouvelle.
- Envoyer `enseignant_id: null` termine l’affectation et retire l’enseignant de la matière.
- Omettre `enseignant_id` lors d’une modification conserve les affectations.
- Les affectations terminées restent dans l’historique. L’enregistrement est atomique : en cas de conflit ou d’enseignant inactif, aucune modification partielle n’est conservée.

## API des affectations

Base : `/api/v1/administration/affectations-enseignants`.

```json
{
  "enseignant_id": 12,
  "portee": "matiere",
  "id_matiere": 4,
  "date_debut": "2026-09-15"
}
```

Pour `portee: "cours"`, ajouter `id_cours`. Le cours doit appartenir à la matière. Les doublons de couverture sont refusés sur toute la période, indépendamment des anciennes années enregistrées.

La liste, les options et le tableau de bord fonctionnent sans `id_annee_academique`. Ce paramètre, s’il est encore envoyé par un ancien client, est ignoré ; il n’est plus exposé dans les réponses.

`PATCH /{id}/terminer` reçoit `date_fin` et éventuellement `motif`. La fin ne peut précéder la prise d’effet ni les notes déjà saisies pendant l’affectation. Elle peut dépasser la fin d’une année académique. La date de fin est exclusive : une affectation terminée aujourd’hui n’est plus en cours aujourd’hui.

Dans `/api/v1/enseignant/mes-cours`, les affectations ne sont plus filtrées par année. Le filtre d’année existant sert seulement aux créneaux, promotions et indicateurs de progression.

## Migration

Exécuter `php artisan migrate`. La colonne historique `id_annee_academique` devient facultative, sans clé étrangère, et n’est plus utilisée par les affectations. Ses anciennes valeurs sont conservées.

La migration crée également les affectations manquantes des matières ayant déjà un enseignant, à la date de migration. Elle conserve les affectations existantes et ne crée rien si une affectation de matière ou de cours couvre déjà cette matière aujourd’hui ou dans le futur.
