# Demandes de correction des notes — enseignant

Toutes les routes exigent `Authorization: Bearer <token>` et un compte actif avec le rôle `ENSEIGNANT`. L'identité du demandeur est celle du compte connecté.

## Parcours du formulaire

1. Charger les feuilles transmises avec `GET /api/v1/enseignant/transmissions-notes/feuilles`. Les feuilles contiennent `id_seance`, matière, cours, promotion et les notes avec leur identifiant, évaluation et étudiant. Le paramètre `id_seance` permet de charger la séance sélectionnée. Une feuille peut contenir plusieurs évaluations : afficher les notes de l'évaluation choisie.
2. Envoyer uniquement les notes modifiées avec `POST /api/v1/enseignant/corrections-notes` :

```json
{
  "id_seance": 1,
  "motif": "Erreur de report après recomptage des copies",
  "notes": [
    { "id_note": 12, "note_proposee": 16.5 },
    { "id_note": 13, "note_proposee": 12 }
  ]
}
```

La réponse `201` contient `corrections` et `nombre_demandes`. Chaque note possède sa propre demande et son propre identifiant. L'envoi est atomique : si une note est invalide, aucune demande n'est créée. Les notes actuelles restent inchangées jusqu'à autorisation et application.

Le format unitaire reste disponible : `id_seance`, `id_note`, `note_proposee`, `motif` ; sa réponse contient `correction`.

## Liste, détails et modification

| Méthode | Route `/api/v1/enseignant` | Usage |
| --- | --- | --- |
| GET | `/corrections-notes` | Liste paginée de mes demandes |
| GET | `/corrections-notes/{id}` | Détails et historique |
| PUT | `/corrections-notes/{id}` | Modifier `note_proposee` et `motif` avant décision |
| POST | `/corrections-notes/{id}/appliquer` | Appliquer la note autorisée, sans corps |

Filtres de liste : `search` (nom, prénoms, matricule, matière ou cours), `id_seance`, `id_note`, `statut`, `page` et `per_page` (1 à 100). La réponse suit la pagination Laravel avec `data`, `total`, `current_page`, `last_page`.

Les lignes de liste et les détails incluent `note_initiale`, `note_proposee`, `motif`, `statut`, `modifiable`, `evaluation`, `etudiant` et `feuille_notes` (matière, cours, promotion et niveau). `modifiable` indique un statut `en_attente` ; l'enregistrement revérifie aussi l'affectation et l'état de la note. Pour modifier plusieurs notes envoyées ensemble, appeler le PUT sur chacun des identifiants retournés.

```json
{
  "note_proposee": 17,
  "motif": "Barème vérifié après recomptage"
}
```

## Autorisation et contrôles

L'administration autorise ou rejette chaque demande via `POST /api/v1/administration/corrections-notes/{id}/autoriser` ou `/rejeter` (avec `motif`). Les statuts sont `en_attente`, `transmise_direction`, `autorisee`, `appliquee`, `rejetee`.

L'enseignant ne peut consulter et modifier que ses demandes. Pour créer, modifier ou appliquer une correction, il doit avoir une affectation active sur la matière ou le cours, un créneau correspondant à la promotion et à l'année académique, et être l'enseignant de la séance. Toutes les notes envoyées doivent correspondre à `id_seance`.

Les notes doivent être comprises entre 0 et 20, avec deux décimales au maximum, et différer de la valeur actuelle. Le motif est obligatoire, limité à 5 000 caractères. Une note ayant déjà une demande en attente ou autorisée ne peut pas faire l'objet d'une seconde demande. La création accepte jusqu'à 100 notes distinctes. Une feuille en brouillon ou rejetée par le secrétariat ne passe pas par ce circuit de correction.

Une correction autorisée ne peut être appliquée qu'une fois. Si la note initiale a changé, l'application est refusée. Après application, la feuille retourne au statut `transmise` pour vérification par le secrétariat. Chaque demande, modification et décision est tracée.

Erreurs : `401` sans authentification, `403` pour un rôle interdit, `404` pour une ressource inaccessible et `422` pour des données invalides ou un état incompatible.

Le secr?tariat transmet les demandes v?rifi?es ? la direction (`transmise_direction`) ou les rejette. Son appel ? `/valider` ou `/autoriser` transmet la demande sans autorisation finale. La direction (ou ADMIN) autorise ou rejette ; apr?s transmission, la demande n?est plus modifiable par l?enseignant. Une demande `transmise_direction` bloque ?galement toute seconde demande pour la m?me note. Voir [le circuit de validation](validation-corrections-notes-api.md).
