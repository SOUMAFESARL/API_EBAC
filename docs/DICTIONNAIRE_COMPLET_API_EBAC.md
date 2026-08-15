# Dictionnaire complet des API EBAC

Version : API v1
Base URL : `https://api-ebac.severinzran.ci/api/v1`
Format : JSON UTF-8, sauf téléversement de photo (`multipart/form-data`).

## 1. Conventions générales

Routes protégées :

```http
Accept: application/json
Content-Type: application/json
Authorization: Bearer {token}
```

Codes HTTP : `200` succès, `201` création, `202` OTP envoyé, `401` non authentifié, `403` accès refusé, `404` ressource absente, `422` validation, `429` limite atteinte, `500` erreur serveur.

Erreur de validation standard :

```json
{
  "message": "Les données fournies sont invalides.",
  "errors": {
    "email": ["Le champ email est obligatoire."]
  }
}
```

Les codes `USR-*`, `ROL-*`, `PER-*` et `MEN-*` sont générés par l’API. Le frontend ne doit jamais les envoyer.

## 2. Dictionnaire des objets

### 2.1 Utilisateur

| Champ | Type | Null | Source | Description |
|---|---|---:|---|---|
| `id` | entier | non | API | Identifiant du compte |
| `civilite_id` | entier | oui | Front | Référence à la civilité |
| `matricule` | chaîne | oui | API | Matricule interne |
| `code` | chaîne | non | API | Code automatique du nouveau compte |
| `user_code` | chaîne | oui | API | Code de l’utilisateur créateur |
| `user_id` | entier | oui | API | ID de l’utilisateur créateur |
| `nom` | chaîne(150) | non | Front | Nom |
| `prenoms` | chaîne(150) | non | Front | Prénoms |
| `email` | email(150) | non | Front | Email unique |
| `photo` | chaîne | oui | API | Chemin de la photo |
| `photo_url` | URL | oui | API | URL publique de la photo |
| `id_role` | entier | non | Front | Rôle attribué |
| `is_active` | booléen | non | Front | Compte activé |
| `statut` | enum | non | Front | `Actif`, `Suspendu`, `Bloqué`, `Désactivé` |
| `deux_fa_active` | booléen | non | Front | Double authentification active |
| `tentatives_echouees` | entier | non | API | Échecs de connexion |
| `cree_le` | ISO-8601 | oui | API | Date de création |
| `derniere_connexion` | ISO-8601 | oui | API | Dernière connexion |
| `role` | objet | oui | API | `{id, code, libelle}` |
| `civilite` | objet | oui | API | `{id, code, name, abreviation}` |

### 2.2 Rôle

| Champ | Type | Source | Description |
|---|---|---|---|
| `id` | entier | API | Identifiant |
| `code` | chaîne | API | Automatique : `ROL-000001` |
| `libelle` | chaîne(80) | Front | Nom du rôle |
| `description` | chaîne(255) | Front | Description facultative |
| `permissions` | tableau | Front/API | Permissions associées |
| `permissions_count` | entier | API | Nombre de permissions |

### 2.3 Permission

| Champ | Type | Source | Description |
|---|---|---|---|
| `id` | entier | API | Identifiant |
| `code` | chaîne | API | Automatique : `PER-000001` |
| `libelle` | chaîne(120) | Front | Nom de la permission |
| `description` | chaîne(255) | Front | Description facultative |
| `actions` | tableau | Front/API | Actions autorisées |
| `roles` | tableau | API | Rôles utilisant la permission |

### 2.4 Action

| Champ | Type | Source | Description |
|---|---|---|---|
| `id` | entier | API | Identifiant |
| `code` | chaîne(30) | API | Généré depuis le libellé |
| `libelle` | chaîne(120) | Front | Nom de l’action |
| `description` | chaîne(255) | Front | Description facultative |
| `actif` | booléen | Front | État de l’action |
| `permissions_count` | entier | API | Nombre de permissions associées |

Actions initiales : `AJOUTER`, `SUPPRIMER`, `MODIFIER`, `VOIR`, `IMPRIMER`, `TELECHARGER`.

### 2.5 Menu

| Champ | Type | Source | Description |
|---|---|---|---|
| `id` | entier | API | Identifiant |
| `id_parent` | entier/null | Front | Menu parent |
| `code` | chaîne | API | Automatique : `MEN-000001` |
| `libelle` | chaîne(150) | Front | Texte du sidebar |
| `description` | chaîne(255) | Front | Description |
| `route` | chaîne(180) | Front | Route frontend |
| `route_active` | chaîne(180) | Front | Motif de route active |
| `icone` | chaîne(100) | Front | Nom de l’icône |
| `groupe` | chaîne(100) | Front | Groupe d’affichage |
| `ordre` | entier 0..65535 | Front | Ordre d’affichage |
| `visible` | booléen | Front | Visible dans le sidebar |
| `actif` | booléen | Front | Menu actif |
| `permissions` | tableau | Front/API | Permissions nécessaires |
| `enfants` | tableau | API | Sous-menus |

### 2.6 Pagination

```json
{
  "current_page": 1,
  "data": [],
  "first_page_url": "https://api-ebac.severinzran.ci/api/v1/administration/roles?page=1",
  "from": 1,
  "last_page": 1,
  "last_page_url": "...page=1",
  "links": [],
  "next_page_url": null,
  "path": ".../administration/roles",
  "per_page": 15,
  "prev_page_url": null,
  "to": 1,
  "total": 1
}
```

## 3. Authentification

### POST `/auth/connexion`

Public. Limite : 5 requêtes/minute.

```json
{
  "email": "admin@ebac.ci",
  "password": "password",
  "nom_appareil": "Frontend EBAC"
}
```

Réponse `202` :

```json
{
  "message": "Un code OTP a été envoyé à votre adresse e-mail.",
  "otp_requis": true,
  "id_tentative": 25,
  "expire_dans": 600
}
```

### POST `/auth/confirmer-otp`

```json
{"id_tentative": 25, "code_otp": "123456"}
```

Réponse `200` :

```json
{
  "message": "Connexion réussie.",
  "token": "1|jeton-sanctum",
  "token_type": "Bearer",
  "redirect_to": "/dashboard/index",
  "utilisateur": {
    "id": 1,
    "code": "USR-000001",
    "nom": "Zran",
    "prenoms": "Severin",
    "email": "admin@ebac.ci",
    "id_role": 1,
    "is_active": true,
    "statut": "Actif",
    "role": {"id": 1, "code": "ADMIN", "libelle": "Administrateur"}
  }
}
```

### GET `/auth/profil`

Protégé. Réponse `200` : `{"utilisateur": {…objet Utilisateur…}}`.

### POST `/auth/deconnexion`

Protégé. Réponse `200` :

```json
{"message": "Déconnexion réussie."}
```

### POST `/auth/deconnexion-globale`

Protégé. Supprime tous les jetons. Réponse `200` :

```json
{"message": "Déconnexion de tous les appareils réussie."}
```

## 4. Mot de passe oublié

### POST `/auth/mot-de-passe-oublie`

```json
{"email": "utilisateur@ebac.ci"}
```

Réponse `200` identique que l’email existe ou non :

```json
{"message": "Si cette adresse existe, un code de réinitialisation a été envoyé."}
```

### POST `/auth/verifier-code-reinitialisation`

```json
{"email": "utilisateur@ebac.ci", "code": "915370"}
```

Réponse `200` :

```json
{
  "message": "Code vérifié avec succès.",
  "reset_token": "jeton-temporaire",
  "expire_dans": 600
}
```

### POST `/auth/reinitialiser-mot-de-passe`

```json
{
  "email": "utilisateur@ebac.ci",
  "reset_token": "jeton-temporaire",
  "password": "NouveauMotDePasse123!",
  "password_confirmation": "NouveauMotDePasse123!"
}
```

Réponse `200` :

```json
{"message": "Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter."}
```

## 5. Comptes utilisateurs — permission `COMPTE_GERER`

### GET `/administration/comptes`

Query : `recherche`, `statut`, `id_role`, `is_active`, `par_page`, `page`.
Réponse `200` : pagination dont `data` contient des objets Utilisateur.

### GET `/administration/comptes/create`

Réponse `200` :

```json
{
  "roles": [{"id": 1, "code": "ADMIN", "libelle": "Administrateur"}],
  "civilites": [{"id": 1, "code": "M", "name": "Monsieur", "abreviation": "M."}],
  "statuts": ["Actif", "Suspendu", "Bloqué", "Désactivé"]
}
```

### POST `/administration/comptes`

Utiliser `multipart/form-data` si `photo` est présente.

| Champ | Règle |
|---|---|
| `civilite_id` | facultatif, existe dans `civilite` |
| `nom`, `prenoms` | obligatoires, chaîne, max 150 |
| `email` | obligatoire, email unique, max 150 |
| `id_role` | obligatoire, rôle existant |
| `photo` | jpg/jpeg/png/webp, max 2 Mo |
| `is_active`, `deux_fa_active` | booléens facultatifs |
| `statut` | enum facultatif |
| `code`, `user_code`, `user_id` | interdits ; calculés par l’API |

Réponse `201` :

```json
{
  "message": "Compte créé avec succès. Les identifiants temporaires ont été envoyés par e-mail.",
  "utilisateur": {"id": 12, "code": "USR-000012", "user_id": 1, "user_code": "USR-ADMIN", "nom": "Zran", "prenoms": "Severin", "email": "severin@ebac.ci"}
}
```

### GET `/administration/comptes/{id}`

Réponse `200` : `{"utilisateur": {…objet Utilisateur avec rôle et civilité…}}`.

### GET `/administration/comptes/{id}/edit`

Réponse `200` : utilisateur, rôles, civilités et statuts disponibles.

### PUT/PATCH `/administration/comptes/{id}`

Champs de création facultatifs, plus `password` (min 8) et `password_confirmation`.
Réponse `200` : `{"message":"Compte modifié avec succès.","utilisateur":{…}}`.

### DELETE `/administration/comptes/{id}`

Réponse `200` : `{"message":"Compte supprimé avec succès."}`.
Réponse `422` si l’utilisateur tente de supprimer son propre compte.

## 6. Profil utilisateur

### GET `/administration/profil`

Réponse `200` : `{"utilisateur": {…}}`.

### GET `/administration/profil/edit`

Réponse `200` : utilisateur et civilités disponibles.

### PUT/PATCH `/administration/profil`

Champs : `civilite_id`, `nom`, `prenoms`, `email`, `photo`, `mot_de_passe_actuel`, `password`, `password_confirmation`. Le mot de passe actuel est obligatoire si un nouveau mot de passe est envoyé.
Réponse `200` : `{"message":"Profil modifié avec succès.","utilisateur":{…}}`.

## 7. Rôles — permission `ROLE_GERER`

### GET `/administration/roles`

Query : `recherche`, `par_page`, `page`. Réponse `200` : pagination avec rôles, `permissions` et `permissions_count`.

### POST `/administration/roles`

```json
{"libelle":"Gestionnaire","description":"Gestion des étudiants","permission_ids":[1,2]}
```

Réponse `201` :

```json
{"message":"Rôle créé avec succès.","role":{"id":2,"code":"ROL-000001","libelle":"Gestionnaire","description":"Gestion des étudiants","permissions":[{"id":1,"code":"PER-000001","libelle":"Étudiants"}]}}
```

### GET `/administration/roles/{id}`

Réponse `200` : `{"role":{…rôle avec permissions…}}`.

### PUT/PATCH `/administration/roles/{id}`

Champs : `libelle`, `description`, `permission_ids`. `code` interdit.
Réponse `200` : `{"message":"Rôle modifié avec succès.","role":{…}}`.

### PUT `/administration/roles/{id}/permissions`

```json
{"permission_ids":[1,3,5]}
```

Réponse `200` : `{"message":"Permissions du rôle mises à jour.","role":{…rôle avec permissions…}}`.

### DELETE `/administration/roles/{id}`

Réponse `200` : `{"message":"Rôle supprimé avec succès."}`. `ADMIN` et un rôle attribué à des comptes ne peuvent pas être supprimés (`422`).

## 8. Permissions — permission `PERMISSION_GERER`

### GET `/administration/permissions`

Query : `recherche`, `par_page`, `page`. Réponse `200` : pagination de permissions.

### POST `/administration/permissions`

```json
{"libelle":"Gestion des étudiants","description":"Accès au module étudiants"}
```

Réponse `201` :

```json
{"message":"Permission créée avec succès.","permission":{"id":1,"code":"PER-000001","libelle":"Gestion des étudiants","description":"Accès au module étudiants"}}
```

### GET `/administration/permissions/{id}`

Réponse `200` :

```json
{"permission":{"id":1,"code":"PER-000001","libelle":"Gestion des étudiants","roles":[],"actions":[{"id":1,"code":"AJOUTER","libelle":"Ajouter","actif":true}]}}
```

### PUT/PATCH `/administration/permissions/{id}`

Champs : `libelle`, `description`. `code` interdit.
Réponse `200` : `{"message":"Permission modifiée avec succès.","permission":{…}}`.

### PUT `/administration/permissions/{id}/actions`

```json
{"action_ids":[1,3,4,5]}
```

Réponse `200` : `{"message":"Actions de la permission mises à jour.","permission":{…permission avec actions…}}`.

### DELETE `/administration/permissions/{id}`

Réponse `200` : `{"message":"Permission supprimée avec succès."}`. Réponse `422` si elle est encore attribuée à un rôle.

## 9. Actions — permission `ACTION_GERER`

### GET `/administration/actions`

Réponse `200` :

```json
{"actions":[{"id":1,"code":"AJOUTER","libelle":"Ajouter","description":"Créer un nouvel enregistrement.","actif":true,"permissions_count":0}]}
```

### POST `/administration/actions`

```json
{"libelle":"Valider","description":"Valider un dossier","actif":true}
```

Réponse `201` : `{"message":"Action créée avec succès.","action":{"id":7,"code":"VALIDER","libelle":"Valider","description":"Valider un dossier","actif":true}}`.

### GET `/administration/actions/{id}`

Réponse `200` : `{"action":{…action avec permissions…}}`.

### PUT/PATCH `/administration/actions/{id}`

Champs : `libelle`, `description`, `actif`. `code` interdit et reste immuable.
Réponse `200` : `{"message":"Action modifiée avec succès.","action":{…}}`.

### DELETE `/administration/actions/{id}`

Réponse `200` : `{"message":"Action supprimée avec succès."}`. Réponse `422` si elle est attribuée à une permission.

## 10. Menus — permission `MENU_GERER`

### GET `/administration/menus`

Réponse `200` : `{"menus":[{…menu avec permissions…}]}`.

### POST `/administration/menus`

```json
{
  "id_parent": null,
  "libelle": "Étudiants",
  "description": "Gestion des étudiants",
  "route": "/etudiants",
  "route_active": "/etudiants*",
  "icone": "users",
  "groupe": "Scolarité",
  "ordre": 10,
  "visible": true,
  "actif": true,
  "permission_ids": [1]
}
```

Réponse `201` : `{"message":"Menu créé avec succès.","menu":{"id":1,"code":"MEN-000001","libelle":"Étudiants","route":"/etudiants","permissions":[…]}}`.

### GET `/administration/menus/{id}`

Réponse `200` : `{"menu":{…menu avec permissions et enfants…}}`.

### PUT/PATCH `/administration/menus/{id}`

Tous les champs de création sont facultatifs. `code` interdit. `id_parent` ne peut pas désigner le menu lui-même.
Réponse `200` : `{"message":"Menu modifié avec succès.","menu":{…}}`.

### DELETE `/administration/menus/{id}`

Réponse `200` : `{"message":"Menu supprimé avec succès."}`.

## 11. Navigation

### GET `/navigation/sidebar`

Protégé. Retourne uniquement les menus actifs et visibles autorisés par le rôle de l’utilisateur.

```json
{
  "menus": [
    {
      "id": 1,
      "code": "MEN-000001",
      "libelle": "Étudiants",
      "route": "/etudiants",
      "route_active": "/etudiants*",
      "icone": "users",
      "groupe": "Scolarité",
      "ordre": 10,
      "enfants": []
    }
  ]
}
```

## 12. Catalogue exhaustif des URL

| Méthode | URL |
|---|---|
| POST | `/auth/connexion` |
| POST | `/auth/confirmer-otp` |
| POST | `/auth/mot-de-passe-oublie` |
| POST | `/auth/verifier-code-reinitialisation` |
| POST | `/auth/reinitialiser-mot-de-passe` |
| GET | `/auth/profil` |
| POST | `/auth/deconnexion` |
| POST | `/auth/deconnexion-globale` |
| GET | `/navigation/sidebar` |
| GET, POST | `/administration/comptes` |
| GET | `/administration/comptes/create` |
| GET, PUT, PATCH, DELETE | `/administration/comptes/{id}` |
| GET | `/administration/comptes/{id}/edit` |
| GET, PUT, PATCH | `/administration/profil` |
| GET | `/administration/profil/edit` |
| GET, POST | `/administration/roles` |
| GET, PUT, PATCH, DELETE | `/administration/roles/{id}` |
| PUT | `/administration/roles/{id}/permissions` |
| GET, POST | `/administration/permissions` |
| GET, PUT, PATCH, DELETE | `/administration/permissions/{id}` |
| PUT | `/administration/permissions/{id}/actions` |
| GET, POST | `/administration/actions` |
| GET, PUT, PATCH, DELETE | `/administration/actions/{id}` |
| GET, POST | `/administration/menus` |
| GET, PUT, PATCH, DELETE | `/administration/menus/{id}` |

## 13. Sécurité et règles d’intégration

- Le rôle `ADMIN` contourne les contrôles de permission métier.
- Les autres rôles doivent posséder `COMPTE_GERER`, `ROLE_GERER`, `PERMISSION_GERER`, `ACTION_GERER` ou `MENU_GERER` selon le module.
- Les tableaux `permission_ids` et `action_ids` remplacent entièrement les associations existantes.
- Un tableau vide retire toutes les associations.
- Ne jamais stocker le mot de passe ou l’OTP dans le navigateur.
- Conserver le token dans un stockage adapté au niveau de sécurité du frontend.
- Toujours traiter les réponses `401`, `403`, `422` et `429`.
