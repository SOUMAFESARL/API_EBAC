# Tableau des notes par matière

`GET /api/v1/administration/notes-matieres?id_promotion=22&id_annee_academique=16`

Authentification Bearer, compte actif. Rôles : ADMIN, SECRETARIAT, SECRETAIRE_ACADEMIQUE, DIRECTION.

Paramètres obligatoires : `id_promotion`, `id_annee_academique`. Filtre facultatif : `id_matiere`. Pagination des matières : `page`, `per_page` (20 par défaut, maximum 100).

La réponse contient `promotion`, `annee_academique`, `matieres` et `meta`. Chaque matière contient son seuil `note_validation`, son effectif et un tableau `etudiants` : numéro, identifiant étudiant, nom, prénoms, matricule, note, nombre de notes validées, décision et libellé.

Seules les notes des feuilles **validées par la direction** contribuent au tableau. La validation secrétariat seule ne constitue pas la validation finale. Le GET calcule les tableaux à partir des notes existantes : dès la validation finale, les notes sont visibles automatiquement. Aucune migration ou copie des notes n’est nécessaire.

La note de matière correspond à la moyenne arithmetique des notes validees, avec le meme poids pour chaque note. Les notes des cours sont rattachées à leur matière par le module. La moyenne est affichée sur deux décimales ; la décision compare la moyenne non arrondie au seuil de validation de la matière (10 si non renseigné).

Décisions : `valide` / « Validé », `ajourne` / « Ajourné », `en_attente` / « En attente ». Tous les étudiants inscrits dans cette promotion pour cette année sont affichés. Un étudiant sans note validée a `note: null`. Les matières sans aucune note validée ne sont pas retournées.

Les feuilles en brouillon, transmises, rejetées ou en attente de direction sont exclues. Après application d’une correction, la feuille retournant au contrôle cesse de contribuer jusqu’à sa revalidation. Les bulletins ne sont pas régénérés par cette API.

Cette API fournit le tableau demandé dans la capture ; les décisions sont calculées et ne constituent pas une décision annuelle de passage. Elle ne modifie aucune note.
