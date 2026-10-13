# Validation des corrections — secrétariat et direction

Authentification : `Authorization: Bearer <token>`. Rôles autorisés avec un compte actif : `ADMIN`, `SECRETARIAT`, `SECRETAIRE_ACADEMIQUE`, `DIRECTION`.

Toutes les routes ci-dessous commencent par `/api/v1/administration/corrections-notes`.

| Méthode | Route | Usage |
| --- | --- | --- |
| GET | `/` | Consulter toutes les demandes, avec pagination |
| GET | `/{id}` | Consulter les détails et l'historique |
| POST | `/{id}/valider` | Valider une demande en attente, sans corps |
| POST | `/{id}/autoriser` | Même action que `/valider`, route existante conservée |
| POST | `/{id}/rejeter` | Rejeter une demande en attente avec un motif obligatoire |
| POST | `/{id}/appliquer` | Appliquer la note autorisée : ADMIN ou secrétariat |

## Liste et détails

Filtres : `statut` (`en_attente`, `autorisee`, `appliquee`, `rejetee`), `id_note`, `id_seance`, `demande_par`, `search`, `page`, `per_page` (1 à 100).

Exemple : `GET /api/v1/administration/corrections-notes?statut=en_attente&search=KOUAME`.

La pagination Laravel contient `data`, `total`, `current_page`, `last_page`. Chaque ligne représente une correction d'une note. Un envoi enseignant contenant plusieurs notes produit plusieurs identifiants à traiter séparément.

La liste et les détails incluent le demandeur et son rôle, l'étudiant et son matricule, l'évaluation, les notes initiale/proposée/finale, le motif, la matière, le cours, la promotion et le niveau. Le champ `actions` indique `peut_valider`, `peut_rejeter` et `peut_appliquer` selon le rôle connecté et le statut. Ces indicateurs ne remplacent pas les contrôles effectués à l'enregistrement.

Le détail contient aussi `historique`, avec l'acteur, l'action, la date et les détails ; le motif de rejet se trouve dans la trace `rejetee`.

## Décision

`POST /api/v1/administration/corrections-notes/12/valider` sans corps passe la demande de `en_attente` à `autorisee`. La réponse contient `message` et `correction`, notamment `autorisee_par` et `autorisee_le`. La note actuelle reste inchangée à cette étape.

`POST /api/v1/administration/corrections-notes/12/rejeter` :

```json
{
  "motif": "La copie ne justifie pas la correction proposée."
}
```

Le motif est obligatoire, non vide et limité à 5 000 caractères. La demande passe à `rejetee`, avec une trace de la décision. Le motif initial de l'enseignant reste conservé. Une demande déjà autorisée, appliquée ou rejetée ne peut plus être validée ou rejetée (`422`).

Après autorisation, le secrétariat ou l'ADMIN peut appliquer la correction par `/{id}/appliquer`. La DIRECTION autorise ou rejette et ne peut pas appeler cette route (`403`). L'enseignant demandeur dispose également de sa route d'application existante. L'application met à jour la note et renvoie la feuille au statut `transmise` pour contrôle. Ce circuit ne régénère pas automatiquement un bulletin.

Chaque décision utilise l'identité du compte connecté et une transaction avec verrouillage pour empêcher une seconde décision sur la même demande. Erreurs : `401` sans authentification, `403` si accès interdit, `404` si demande introuvable, `422` si motif ou statut invalide.
