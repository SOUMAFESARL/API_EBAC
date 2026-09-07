# API des nouvelles admissions

Préfixe : `/api/v1/administration/nouvelles-admissions`.
Toutes les routes nécessitent `Authorization: Bearer <token>`, un compte actif et le rôle `ADMIN`, `SECRETARIAT` ou `SECRETAIRE_ACADEMIQUE`.

## Importer

`POST /importer`, corps **multipart/form-data** :

| Champ | Contenu |
| --- | --- |
| `annee_entree` | Obligatoire, entier (ex. `2026` pour la rentrée 2026-2027) |
| `liste` | Obligatoire, fichier `.xlsx`, `.xls` ou `.csv`, 10 Mo maximum |
| `document_pdf` | Facultatif, PDF signé, 10 Mo maximum (ancien champ `arrete` renommé) |

Première feuille uniquement, première ligne contenant les en-têtes : **Nom et Prénoms**, **Région**, **Paroisse**, **Situation matrimoniale**. Les quatre colonnes doivent exister ; seul le nom est obligatoire sur chaque ligne. Les en-têtes sans accents et `nom_prenoms` sont aussi acceptés. CSV avec virgules ou points-virgules. Limite : 5000 admis et 30 colonnes.

Réponse HTTP 201 : `message`, `annee_entree`, `import_id`, `importes`, `doublons_ignores`.
Une ligne invalide annule tout l'import (HTTP 422 avec le numéro de ligne). Les formules sont refusées dans les colonnes importées.
Dans une même rentrée, un nom + région + paroisse identiques (casse et espaces normalisés) est considéré comme doublon ; ses coordonnées existantes sont conservées. Deux homonymes de la même paroisse et région nécessitent une distinction dans la liste avant import. Un nouvel import ajoute des admis sans supprimer les précédents.

L'arrêté est conservé dans le stockage privé et rattaché à l'import et à sa rentrée. Plusieurs arrêtés peuvent donc être conservés pour une rentrée. Aucun compte étudiant ni dossier de préinscription n'est créé automatiquement.

## Afficher la page

`GET /?annee_entree=2026&recherche=YAO&par_page=15&page=1`

La rentrée par défaut commence dans l'année civile courante. Réponse :

- `annee_entree`, `rentree`, `annees_disponibles` ;
- `statistiques` : `total`, `dossier_depose`, `sans_coordonnees`, `dernier_enregistrement` ;
- `imports` : historique avec `id`, `nom_fichier`, `created_at`, `arrete_url` ;
- `admis` : pagination Laravel (`data`, `total`, `current_page`, etc.).

Les statistiques concernent toute la rentrée sélectionnée ; la recherche filtre la liste par nom, région ou paroisse. « Sans coordonnées » signifie sans téléphone **et** sans adresse. Le champ `dossier_depose` est un suivi manuel indépendant des dossiers étudiants existants.

## Compléter les coordonnées

`PATCH /{id}` en JSON, champs facultatifs :

```json
{
  "telephone": "0102030405",
  "adresse": "Abidjan",
  "email": "admis@example.com",
  "dossier_depose": true
}
```

`null` efface une coordonnée. Réponse : `message`, `admis`.

## Documents

- `GET /pdf?annee_entree=2026` : liste des admis en PDF ; accepte aussi `recherche`.
- `GET /imports/{id}/document-pdf` : afficher le PDF enregistré (`Content-Disposition: inline`).
- `GET /imports/{id}/document-pdf/telecharger` : télécharger le PDF enregistré (`Content-Disposition: attachment`).
- `GET /imports/{id}/arrete` : ancienne route conservée pour compatibilité.

`{id}` est l’`import_id` retourné par l’import. Les réponses de l’import et de l’historique fournissent `document_pdf_url` et `document_pdf_telechargement_url` (null si aucun PDF). Un document absent retourne 404. Les documents existants restent disponibles ; aucun changement de base de données n’est nécessaire.

Télécharger ces documents avec le jeton Bearer (par exemple requête du frontend puis création d'un objet Blob).

## Installation

Appliquer la migration avec `php artisan migrate`. La documentation Swagger contient les cinq routes.
