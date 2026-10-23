# Configuration de l’école

Une configuration unique, sans identifiant dans l'URL.

| Méthode | URL | Accès |
| --- | --- | --- |
| GET | `/api/v1/parametres/ecole` | Tout compte authentifié actif |
| POST | `/api/v1/parametres/ecole` | ADMIN, DIRECTION |
| PATCH / PUT | `/api/v1/parametres/ecole` | ADMIN, DIRECTION |

Envoyer `Authorization: Bearer <token>` et `Accept: application/json`.
GET retourne `{"ecole":null}` avant le premier enregistrement. POST crée la
configuration (201) ou la modifie (200). PATCH et PUT permettent également une
mise à jour partielle. Les champs omis sont conservés.

`nom_ecole` (180 caractères) et `code_ecole` (50 caractères) sont obligatoires à
la création et ne peuvent pas être effacés. Autres champs : `slogan`, `adresse`,
`fondateur`, `directeur_actuel`, `situation_geographique`, `ville`, `region`
(255 caractères), `email` (150), `telephone`, `cellulaire` (30) et `biographie`
(50 000, texte brut). « Biographie » désigne la présentation ou l'historique de l'école.
Les champs facultatifs peuvent être effacés avec `null` en JSON.

Exemple de création JSON :

```json
{
  "nom_ecole": "École Biblique de l’Alliance Chrétienne",
  "code_ecole": "EBAC",
  "slogan": "Former pour servir",
  "adresse": "Adresse de l’école",
  "fondateur": "Nom du fondateur",
  "directeur_actuel": "Nom du directeur",
  "situation_geographique": "Quartier et repères",
  "ville": "Abidjan",
  "region": "Région de l’école",
  "email": "contact@example.com",
  "telephone": "+225 2700000000",
  "cellulaire": "+225 0100000000",
  "biographie": "Présentation et historique de l’école."
}
```

Pour les images, utiliser **POST multipart/form-data** avec les champs de type
File `logo`, `favicon`, `signature` (signature de l'école) et `signature_directeur`.
Logo et signatures : PNG, JPEG ou WebP, 2 Mo par fichier. Favicon : ICO, PNG,
JPEG ou WebP, 1 Mo. Un fichier remplacé est supprimé après enregistrement ;
envoyer par exemple `{"signature_directeur":null}` en JSON pour le retirer.

La réponse contient `ecole` avec les informations et `logo_url`, `favicon_url`,
`signature_url`, `signature_directeur_url` (ou null). Les images sont conservées
sur le disque privé `local`. Utiliser les URL signées retournées, valables
15 minutes, pour les afficher. La route `/api/v1/parametres/ecole/fichiers/{type}`
exige les paramètres de signature ; aucun chemin de stockage n'est exposé.

Erreurs : 401 sans authentification, 403 si rôle interdit/compte inactif ou
URL de fichier invalide/expirée, 404 pour un fichier absent, 422 pour un champ
ou fichier invalide ou une mise à jour vide.

Déploiement : exécuter `php artisan migrate --force` puis
`php artisan l5-swagger:generate` sur le serveur. Cette API ne change pas
automatiquement les en-têtes des PDF existants.
