# Unités d’enseignement (UE)

Base : `/api/v1/parametres/unites-enseignement`.

Authentification Sanctum obligatoire, compte actif et rôle ADMIN, SECRETAIRE_ACADEMIQUE, SECRETARIAT ou DIRECTION.

| Méthode | Chemin | Action |
| --- | --- | --- |
| GET | `/` | Liste, filtres `q` ou `recherche` (code/libellé), `code` exact, `statut` |
| POST | `/` | Création (201) |
| GET | `/{id}` | Consultation |
| PUT / PATCH | `/{id}` | Modification des champs transmis |
| DELETE | `/{id}` | Suppression logique |

Exemple de création :

```json
{
  "libelle": "Connaissance de la Bible",
  "code": "BIB",
  "description": null,
  "statut": "Actif"
}
```

`libelle` (180 caractères maximum), `code` (50 maximum, unique) et `statut` (`Actif` ou `Archive`) sont obligatoires à la création. `description` est facultative et accepte `null`. Un code reste réservé après suppression logique.

La liste est retournée dans `unites_enseignement`, une ressource dans `unite_enseignement`. Les erreurs de validation retournent 422, les identifiants absents ou supprimés 404, les accès non authentifiés 401 et les accès interdits 403.

Les champs d’audit sont renseignés par le serveur. Les UE sont stockées dans `unites_enseignement`, indépendamment de l’ancienne table `unites_valeur`.

Installation : `php artisan migrate`.
