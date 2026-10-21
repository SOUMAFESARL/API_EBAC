# Bulletin de notes calculé

`GET /api/v1/administration/bulletins-notes/24?id_promotion=22&id_annee_academique=16`

`24` est l’identifiant de l’étudiant. Promotion et année sont obligatoires. Une inscription correspondante est requise (404 sinon).

Authentification Bearer, compte actif. Rôles : ADMIN, SECRETARIAT, SECRETAIRE_ACADEMIQUE, DIRECTION.

La réponse contient `bulletin` avec étudiant, matricule, photo_identite_url, promotion, année académique, niveau, matières et récapitulatif. Chaque matière contient numéro, code/libellé, coefficient, note, seuil de validation, décision, enseignants et année.

Les notes sont calculées avec le même service que le tableau des notes par matière. Seules les feuilles `validee_direction` contribuent. Les notes des évaluations sont pondérées par les coefficients des cours ; poids 1 pour une note directement liée à une matière. La moyenne générale est la somme des notes de matière multipliées par leurs coefficients, divisée par la somme de ces coefficients positifs, sans arrondi intermédiaire. Les valeurs affichées sont arrondies à deux décimales.

Les enseignants sont les auteurs de transmission des feuilles, à défaut les enseignants des séances, sans doublons. Si aucune identité n’est disponible, le tableau est vide.

Une matière sans note validée pour cet étudiant est absente. Sans note validée, `matieres` est vide et `moyenne_generale` est null. Un bulletin peut donc être partiel tant que toutes les notes n’ont pas été validées. Après correction, une feuille retournée au contrôle est exclue jusqu’à sa revalidation.

`matieres_validees` compte les matières dont la moyenne atteint leur seuil. `credits_valides` est la somme des coefficients des matières validées. `regle_credits` vaut `somme_coefficients_matieres_validees`. Sans matière validée, les crédits valent 0.

Ce GET ne crée ni ne publie de bulletin enregistré. Les API de publication existantes restent distinctes. Aucune migration n’est nécessaire. Swagger : rubrique **Bulletin de notes calcule**.
