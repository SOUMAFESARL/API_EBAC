# Publication des bulletins

Authentification : `Authorization: Bearer <token>`. Compte actif requis.
Rôles autorisés : `ADMIN`, `DIRECTION`, `SECRETARIAT`, `SECRETAIRE_ACADEMIQUE`.

## Liste et détail

`GET /api/v1/administration/bulletins`

Filtres facultatifs : `id_inscription`, `id_promotion`, `id_annee_academique`,
`periode`, `statut` (`Brouillon`, `Généré` ou `Publié`), `page`, `per_page` (1 à 100).
La réponse contient la pagination Laravel et les inscriptions associées.

`GET /api/v1/administration/bulletins/{id}` retourne `bulletin`, ses lignes et matières.

## Publication

`POST /api/v1/administration/bulletins/{id}/publier`

Aucun corps requis. Publie un bulletin existant au statut `Brouillon` ou `Généré`, renseigne
`date_publication` avec la date courante et `updated_by` avec l'utilisateur connecté.
Un bulletin `Généré` est déjà consultable par son étudiant avant publication.
Une nouvelle publication du même bulletin conserve sa date et son auteur.
Cette action ne génère pas de bulletin ni ne recalcule ses notes.

## Génération après validation

La validation d'une feuille par le secrétariat ou la direction génère ou actualise
le bulletin `Annuel` de chaque étudiant inscrit concerné. Son statut vaut `Généré`
et `date_publication` reste vide. La simple transmission de notes non validées ne
les rend pas visibles. Les notes validées au secrétariat restent prises en compte
pendant leur transmission à la direction.

Les notes des feuilles rejetées ou retournées en correction sont retirées des
bulletins générés ; sans aucune note admissible, le bulletin généré est supprimé.
Une nouvelle validation le recrée. Un brouillon annuel existant est remplacé par
les lignes issues des notes validées lorsqu'elles deviennent disponibles.
Les bulletins publiés ou retirés ne sont pas modifiés automatiquement.

Les moyennes par matière utilisent les coefficients des cours ; la moyenne générale
utilise ceux des matières, sans arrondi intermédiaire. Cette génération enregistre
le bulletin en base ; elle ne crée pas de PDF. Aucun rang ni mention n'est inventé.
Les routes étudiant restent `GET /api/v1/etudiant/bulletins` et
`GET /api/v1/etudiant/bulletins/{id}`, avec le token du compte étudiant.

La génération est déclenchée par les prochaines transitions de validation : les
feuilles déjà validées avant cette modification ne sont pas reprises à la connexion.

Réponse 200 : `{"message":"Bulletin publié.","bulletin":{...}}`.
Erreurs : 401 sans authentification, 403 si rôle interdit ou compte inactif,
404 si bulletin inexistant, 422 si statut incompatible ou filtre invalide.
