# Écran du secrétariat : corrections regroupées

Authentification : `Authorization: Bearer <token>`, compte actif. Lecture : ADMIN, SECRETARIAT, SECRETAIRE_ACADEMIQUE, DIRECTION. Application et rejet : ADMIN et secrétariat. Aucune autorisation préalable de la direction.

## Tableau

`GET /api/v1/administration/corrections-notes/groupes?statut=en_attente`

Réponse : `feuilles_corrections` et `meta` (total, page courante, dernière page, taille, from/to). Une ligne par **feuille et évaluation**, uniquement si des demandes de correction existent. Les demandes successives restent dans le même groupe ; leurs dates, identifiants et statuts individuels restent accessibles.

Filtres : `search` (matière, cours, numéro de promotion, enseignant demandeur, évaluation), `statut`, `id_promotion`, `id_annee_academique`, `page`, `per_page` (1–100).

Statuts du groupe : `en_attente` s’il existe une correction encore applicable ; sinon `appliquee` ou `rejetee` si toutes les demandes ont ce statut ; `mixte` si les décisions finales diffèrent. Les anciens statuts `autorisee` et `transmise_direction` sont également applicables par le secrétariat.

## Panneau de détail

`GET /api/v1/administration/corrections-notes/groupes/1?evaluation=Participation`

`1` est **id_feuille_notes**, et non un identifiant de correction. `evaluation` est obligatoire : reprendre exactement la valeur de la liste, en encodant les caractères dans l’URL. Pour une évaluation null, envoyer `?evaluation=`.

La réponse `feuille_corrections` contient :

- `matiere`, `cours`, `promotion`, `enseignant` (si demandeur unique), `enseignants`, `date_demande`.
- `nombre_corrections`, `corrections_en_attente`, `notes_saisies`, `effectif`, `moyenne_cours`.
- `notes` : tous les étudiants ayant une note dans cette évaluation, matricule, nom, prénoms et note actuelle.
- `notes[].corrections` : anciennes notes, notes proposées/finales, motifs, statuts et demandeurs. Tableau vide pour une note sans demande.
- `actions.peut_appliquer`, `actions.peut_rejeter`, `ids_corrections_applicables`, `ids_corrections_rejetables`.
- `historique` : décisions tracées, avec identifiant de correction et acteur.

`effectif` compte les inscrits distincts de la promotion pour l’année académique. `moyenne_cours` correspond aux notes actuelles de l’évaluation ; elle est actualisée après application. Les nombres et les dates sont à formater dans le front.

## Bouton « Appliquer les corrections »

`POST /api/v1/administration/corrections-notes/groupes/1/appliquer`

```json
{
  "evaluation": "Participation",
  "ids_corrections": [12, 13]
}
```

Reprendre `ids_corrections_applicables` du GET. Maximum 100 par appel. L’action traite uniquement les identifiants explicitement sélectionnés : une nouvelle demande arrivée depuis l’affichage reste à vérifier. Un sous-ensemble est également accepté.

Toutes les corrections doivent appartenir à cette feuille et cette évaluation. La note actuelle doit correspondre à la note initiale de chaque demande. Autorisation, mise à jour des notes et traces sont transactionnelles : si une seule demande échoue, **tout est annulé**. Une demande déjà appliquée ne peut pas être appliquée une seconde fois.

Réponse : `message` et `feuille_corrections` actualisée. La feuille de notes revient au contrôle comme dans le circuit individuel existant. Aucun bulletin n’est régénéré automatiquement.

## Bouton « Rejeter »

`POST /api/v1/administration/corrections-notes/groupes/1/rejeter`

```json
{
  "evaluation": "Participation",
  "ids_corrections": [12, 13],
  "motif": "La copie ne justifie pas les notes proposées."
}
```

Reprendre `ids_corrections_rejetables` du GET. Motif non vide, 5 000 caractères maximum. Rejet atomique, sans modification des notes.

Swagger : rubrique **Corrections des notes**, opérations contenant `/groupes`. Les schémas et exemples JSON sont inclus pour les GET et les actions. Les routes individuelles existantes restent disponibles.
