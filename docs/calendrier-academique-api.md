# Paramétrage du calendrier académique

Acc?s r?serv? aux r?les `ADMIN`, `SECRETAIRE_ACADEMIQUE` et `DIRECTION`, avec compte actif authentifi?. Tous les autres r?les re?oivent 403.

Authentification : `Authorization: Bearer <token>` et compte actif, comme les autres routes de paramétrage. Envoyer `Accept: application/json` et `Content-Type: application/json`.

## Années académiques

Base : `/api/v1/parametres/annees-academiques`.

| Méthode | URL | Utilisation |
| --- | --- | --- |
| GET | base | Liste dans `annees_academiques`, triée par début décroissant |
| POST | base | Déclarer une année |
| GET | base/{id} | Lire une année |
| PUT / PATCH | base/{id} | Modifier une année |
| DELETE | base/{id} | Suppression logique de l’année |

```json
{
  "libelle": "2026-2027",
  "date_debut": "2026-09-01",
  "date_fin": "2027-07-30",
  "active": true
}
```

`active` correspond à « Année en cours ». L’activation désactive les autres années dans la même transaction. Les dates doivent être au format `YYYY-MM-DD`. Une modification des bornes de l’année est refusée si elle rend son calendrier invalide.

## Calendrier complet

Base : `/api/v1/parametres/annees-academiques/{id}/calendrier`.

| Méthode | Résultat |
| --- | --- |
| GET | 200 : `annee_academique` et `calendrier` ; 404 si non configuré |
| POST | 201 : création ; 409 si déjà configuré |
| PUT | 200 : remplacement complet ; 404 si non configuré |
| DELETE | 200 : suppression définitive du calendrier et de ses périodes, conserve l’année |

Les boutons Ajouter et Supprimer modifient les tableaux du formulaire. « Enregistrer le calendrier » envoie POST à la première configuration, puis PUT. Toutes les rubriques sont obligatoires ; envoyer `[]` pour une liste vide et `null` en l’absence de grandes vacances. Les modules sont ordonnés selon leur position dans le tableau. Les périodes internes sont recréées lors du PUT, leurs identifiants ne constituent pas un contrat public. Il n’existe pas d’endpoint individuel pour chaque ligne.

```json
{
  "modules": [
    {
      "libelle": "Module 1",
      "date_debut": "2026-09-01",
      "date_fin": "2026-12-20",
      "examens": [{"date_debut": "2026-12-15", "date_fin": "2026-12-20"}],
      "rattrapages": [{"date_debut": "2027-01-05", "date_fin": "2027-01-10"}]
    },
    {
      "libelle": "Module 2",
      "date_debut": "2027-01-11",
      "date_fin": "2027-06-30",
      "examens": [],
      "rattrapages": [{"date_debut": "2027-07-01", "date_fin": "2027-07-10"}]
    }
  ],
  "jours_feries": [{"libelle": "Toussaint", "date": "2026-11-01"}],
  "conges": [{"libelle": "Congés de Noël", "date_debut": "2026-12-21", "date_fin": "2027-01-04"}],
  "grandes_vacances": {"date_debut": "2027-07-31", "date_fin": "2027-08-31"}
}
```

La réponse `calendrier` reprend ces rubriques et ajoute `id`, `id_annee_academique`, `created_by`, `updated_by`, `updated_at`. GET ajoute l’année sous `annee_academique` ; POST et PUT ajoutent un `message`.

## Règles

- Au moins un module ; dates de fin supérieures ou égales aux débuts.
- Modules dans l’année, sans recouvrement, bornes incluses.
- Examens à l’intérieur du module associé.
- Hypothèse retenue pour les sessions ultérieures : rattrapages strictement après la fin du module, dans l’année ; ils peuvent se tenir pendant un autre module.
- Jours fériés et congés dans l’année ; pas de doublon de date pour les jours fériés.
- Au plus une période de grandes vacances, après tous les modules, congés et rattrapages. Elle peut dépasser la fin de l’année académique.
- Enregistrement atomique : une erreur renvoie 422 avec `errors` indexé par champ (ex. `modules.1.date_debut`) et conserve intégralement le calendrier précédent.
- Année supprimée : calendrier conservé en historique, inaccessible par ces endpoints.

Le stockage des jours fériés et congés permet leur utilisation par un futur calcul des séances ; cette API de paramétrage ne calcule pas de planning.

## Stockage et installation

Nouvelles tables : `calendriers_academiques`, `modules_calendrier`, `evenements_calendrier`. Relations Eloquent : `AnneeAcademique::calendrier()`, `CalendrierAcademique::modules()`, `CalendrierAcademique::evenements()`. Les modules du calendrier sont distincts des modules de matières existants.

Appliquer les migrations : `php artisan migrate`.

Tests : `php artisan test --filter="CalendrierAcademiqueApiTest|AnneeAcademiqueApiTest"`.
