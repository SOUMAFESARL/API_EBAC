# Publication des bulletins

Authentification : `Authorization: Bearer <token>`. Compte actif requis.
Rôles autorisés : `ADMIN`, `DIRECTION`, `SECRETARIAT`, `SECRETAIRE_ACADEMIQUE`.

## Liste et détail

`GET /api/v1/administration/bulletins`

Filtres facultatifs : `id_inscription`, `id_promotion`, `id_annee_academique`,
`periode`, `statut` (`Brouillon` ou `Publié`), `page`, `per_page` (1 à 100).
La réponse contient la pagination Laravel et les inscriptions associées.

`GET /api/v1/administration/bulletins/{id}` retourne `bulletin`, ses lignes et matières.

## Publication

`POST /api/v1/administration/bulletins/{id}/publier`

Aucun corps requis. Publie un bulletin existant au statut `Brouillon`, renseigne
`date_publication` avec la date courante et `updated_by` avec l'utilisateur connecté.
Le bulletin devient consultable dans les API étudiant existantes.
Une nouvelle publication du même bulletin conserve sa date et son auteur.
Cette action ne génère pas de bulletin ni ne recalcule ses notes.

Réponse 200 : `{"message":"Bulletin publié.","bulletin":{...}}`.
Erreurs : 401 sans authentification, 403 si rôle interdit ou compte inactif,
404 si bulletin inexistant, 422 si statut incompatible ou filtre invalide.
