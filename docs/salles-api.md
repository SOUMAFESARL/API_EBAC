# API des salles

Acc?s r?serv? aux r?les `ADMIN`, `SECRETAIRE_ACADEMIQUE` et `DIRECTION`, avec compte actif authentifi?. Tous les autres r?les re?oivent 403.

Base : `/api/v1/parametres/salles`. Authentification Bearer Sanctum et compte actif obligatoires, comme les autres paramètres.

| Méthode | URL | Action |
| --- | --- | --- |
| GET | base | Liste paginée |
| POST | base | Création |
| GET | base/{id} | Détail |
| PUT | base/{id} | Modification avec nom et code obligatoires |
| PATCH | base/{id} | Modification partielle |
| DELETE | base/{id} | Suppression logique |

```json
{"nom": "Salle A", "code": "A", "statut": "Actif"}
```

`nom` : texte obligatoire, 180 caractères maximum. `code` : obligatoire, 30 caractères maximum, normalisé en majuscules, unique parmi les salles non supprimées. Un code supprimé peut être réutilisé. `statut` : `Actif` ou `Inactif`, facultatif ; création en `Actif` par défaut, valeur conservée lors d’une modification sans statut.

Liste : `?recherche=salle&statut=Actif&tri=nom&direction=asc&page=1&per_page=15`.
Tri autorisé : `code` (défaut), `nom`, `statut`. Direction : `asc` (défaut) ou `desc`. Pagination de 1 à 100 lignes, 15 par défaut. Recherche sur nom et code, caractères `%` et `_` traités littéralement.

Réponse de liste : `salles` (tableau), `salles_en_service` (nombre global de salles actives non supprimées, indépendant des filtres), `meta` (`current_page`, `last_page`, `per_page`, `total`, `from`, `to`). Les réponses de détail et d’écriture contiennent `salle` ; les écritures ajoutent un `message`.

Chaque salle expose ses champs et les identifiants d’audit, ainsi que `seances_par_semaine: null`. La gestion des séances n’existe pas encore dans ce projet : afficher « Non disponible » pour ce compteur. Les conflits horaires seront contrôlés dans la future API de programmation des séances. Aucun compteur fictif n’est stocké.

Codes HTTP : 201 création, 200 succès, 401 non authentifié, 403 compte inactif, 404 introuvable, 422 validation (`errors` par champ).

Migration : `php artisan migrate --path=database/migrations/2026_09_10_130000_create_salles_table.php`.
Tests : `php artisan test --filter=SalleApiTest`. Swagger : `/api/documentation`.
