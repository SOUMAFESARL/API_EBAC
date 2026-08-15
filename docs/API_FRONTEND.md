# Documentation API EBAC — intégration frontend

## 1. Informations générales

| Élément | Valeur |
|---|---|
| URL de production | `https://api-ebac.severinzran.ci` |
| Base API | `https://api-ebac.severinzran.ci/api/v1` |
| Format | JSON, sauf upload de photo (`multipart/form-data`) |
| Authentification | Laravel Sanctum, jeton Bearer obtenu après OTP |
| CORS | Toutes les origines, méthodes et en-têtes sont autorisés |
| Durée OTP | 10 minutes |
| Tentatives OTP | 5 maximum |

Headers JSON recommandés :

```http
Accept: application/json
Content-Type: application/json
```

Pour une route protégée :

```http
Authorization: Bearer {token}
```

## Catalogue complet des URL

### Référentiel des tags

| Tag | Description | Accès |
|---|---|---|
| `AUTH` | Connexion, OTP, mot de passe et déconnexion | Public ou authentifié selon la route |
| `NAVIGATION` | Construction dynamique du sidebar | Utilisateur authentifié actif |
| `COMPTES` | Administration des comptes utilisateurs | Permission `COMPTE_GERER` ou rôle `ADMIN` |
| `PROFIL` | Consultation et modification de son profil | Utilisateur authentifié actif |
| `ROLES` | Administration des rôles | Permission `ROLE_GERER` ou rôle `ADMIN` |
| `PERMISSIONS` | Administration des permissions | Permission `PERMISSION_GERER` ou rôle `ADMIN` |
| `MENUS` | Administration des éléments du sidebar | Permission `MENU_GERER` ou rôle `ADMIN` |

Chaque endpoint ci-dessous indique son tag d’intégration. Le même tag peut être utilisé dans Swagger/OpenAPI, Postman ou le service API du frontend.

### Authentification

**Tag : `AUTH`**

| Méthode | URL complète |
|---|---|
| POST | `https://api-ebac.severinzran.ci/api/v1/auth/connexion` |
| POST | `https://api-ebac.severinzran.ci/api/v1/auth/confirmer-otp` |
| POST | `https://api-ebac.severinzran.ci/api/v1/auth/mot-de-passe-oublie` |
| POST | `https://api-ebac.severinzran.ci/api/v1/auth/verifier-code-reinitialisation` |
| POST | `https://api-ebac.severinzran.ci/api/v1/auth/reinitialiser-mot-de-passe` |
| POST | `https://api-ebac.severinzran.ci/api/v1/auth/modifier-mot-de-passe` |
| GET | `https://api-ebac.severinzran.ci/api/v1/auth/profil` |
| POST | `https://api-ebac.severinzran.ci/api/v1/auth/deconnexion` |
| POST | `https://api-ebac.severinzran.ci/api/v1/auth/deconnexion-globale` |

### Navigation

**Tag : `NAVIGATION`**

| Méthode | URL complète |
|---|---|
| GET | `https://api-ebac.severinzran.ci/api/v1/navigation/sidebar` |

### Comptes

**Tag : `COMPTES`**

| Méthode | URL complète |
|---|---|
| GET | `https://api-ebac.severinzran.ci/api/v1/administration/comptes` |
| POST | `https://api-ebac.severinzran.ci/api/v1/administration/comptes` |
| GET | `https://api-ebac.severinzran.ci/api/v1/administration/comptes/create` |
| GET | `https://api-ebac.severinzran.ci/api/v1/administration/comptes/{id}` |
| GET | `https://api-ebac.severinzran.ci/api/v1/administration/comptes/{id}/edit` |
| PUT | `https://api-ebac.severinzran.ci/api/v1/administration/comptes/{id}` |
| PATCH | `https://api-ebac.severinzran.ci/api/v1/administration/comptes/{id}` |
| DELETE | `https://api-ebac.severinzran.ci/api/v1/administration/comptes/{id}` |

### Profil

**Tag : `PROFIL`**

| Méthode | URL complète |
|---|---|
| GET | `https://api-ebac.severinzran.ci/api/v1/administration/profil` |
| GET | `https://api-ebac.severinzran.ci/api/v1/administration/profil/edit` |
| PUT | `https://api-ebac.severinzran.ci/api/v1/administration/profil` |
| PATCH | `https://api-ebac.severinzran.ci/api/v1/administration/profil` |

### Rôles

**Tag : `ROLES`**

| Méthode | URL complète |
|---|---|
| GET | `https://api-ebac.severinzran.ci/api/v1/administration/roles` |
| POST | `https://api-ebac.severinzran.ci/api/v1/administration/roles` |
| GET | `https://api-ebac.severinzran.ci/api/v1/administration/roles/{id}` |
| PUT | `https://api-ebac.severinzran.ci/api/v1/administration/roles/{id}` |
| PATCH | `https://api-ebac.severinzran.ci/api/v1/administration/roles/{id}` |
| DELETE | `https://api-ebac.severinzran.ci/api/v1/administration/roles/{id}` |
| PUT | `https://api-ebac.severinzran.ci/api/v1/administration/roles/{id}/permissions` |

### Permissions

**Tag : `PERMISSIONS`**

| Méthode | URL complète |
|---|---|
| GET | `https://api-ebac.severinzran.ci/api/v1/administration/permissions` |
| POST | `https://api-ebac.severinzran.ci/api/v1/administration/permissions` |
| GET | `https://api-ebac.severinzran.ci/api/v1/administration/permissions/{id}` |
| PUT | `https://api-ebac.severinzran.ci/api/v1/administration/permissions/{id}` |
| PATCH | `https://api-ebac.severinzran.ci/api/v1/administration/permissions/{id}` |
| DELETE | `https://api-ebac.severinzran.ci/api/v1/administration/permissions/{id}` |

### Menus

**Tag : `MENUS`**

| Méthode | URL complète |
|---|---|
| GET | `https://api-ebac.severinzran.ci/api/v1/administration/menus` |
| POST | `https://api-ebac.severinzran.ci/api/v1/administration/menus` |
| GET | `https://api-ebac.severinzran.ci/api/v1/administration/menus/{id}` |
| PUT | `https://api-ebac.severinzran.ci/api/v1/administration/menus/{id}` |
| PATCH | `https://api-ebac.severinzran.ci/api/v1/administration/menus/{id}` |
| DELETE | `https://api-ebac.severinzran.ci/api/v1/administration/menus/{id}` |

Dans les URL, remplacer `{id}` par l’identifiant numérique réel de la ressource.

## 2. Format des erreurs

Erreur de validation — HTTP `422` :

```json
{
  "message": "The email field is required.",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

Autres codes importants :

| Code | Signification | Comportement frontend |
|---|---|---|
| `200` | Opération réussie | Exploiter la réponse |
| `201` | Ressource créée | Ajouter la ressource à l’interface |
| `202` | OTP requis | Afficher l’écran de saisie OTP |
| `401` | Jeton absent/invalide | Supprimer le token local et rediriger vers la connexion |
| `403` | Compte inactif ou accès interdit | Afficher le message renvoyé |
| `404` | Ressource inexistante | Afficher une page introuvable |
| `422` | Validation ou règle métier | Afficher `errors` sous les champs |
| `429` | Trop de requêtes | Attendre avant de réessayer |
| `503` | Envoi OTP impossible | Proposer de recommencer la connexion |

## 3. Authentification avec OTP

### 3.1 Demander une connexion

`POST /auth/connexion`

Limite : 5 requêtes par minute.

```json
{
  "email": "utilisateur@exemple.ci",
  "password": "MotDePasse123",
  "nom_appareil": "Application web EBAC"
}
```

`nom_appareil` est facultatif, chaîne de 100 caractères maximum.

Réponse HTTP `202` :

```json
{
  "message": "Un code OTP a été envoyé à votre adresse e-mail.",
  "otp_requis": true,
  "id_tentative": 15,
  "expire_dans": 600
}
```

Le frontend doit conserver temporairement `id_tentative`, afficher l’écran OTP et démarrer un compteur de 600 secondes. Aucun token Sanctum n’est créé à cette étape.

### 3.2 Confirmer l’OTP

`POST /auth/confirmer-otp`

Limite : 10 requêtes par minute.

```json
{
  "id_tentative": 15,
  "code_otp": "317098"
}
```

Contraintes : `id_tentative` entier, `code_otp` exactement 6 chiffres.

Réponse HTTP `200` :

```json
{
  "message": "Connexion réussie.",
  "token": "13|jeton_sanctum",
  "token_type": "Bearer",
  "redirect_to": "/dashboard/index",
  "utilisateur": {
    "id": 1,
    "civilite_id": 1,
    "code": "USR-ADMIN",
    "user_code": "SYSTEM",
    "user_id": "admin",
    "nom": "Zran",
    "prenoms": "Severin",
    "email": "utilisateur@exemple.ci",
    "id_role": 1,
    "is_active": true,
    "statut": "Actif",
    "role": {
      "id": 1,
      "code": "ADMIN",
      "libelle": "Administrateur"
    },
    "civilite": {
      "id": 1,
      "code": "MR",
      "name": "Monsieur",
      "abreviation": "M."
    }
  }
}
```

Stocker `token` puis l’envoyer dans `Authorization: Bearer {token}`. Un OTP expiré, incorrect, déjà consommé ou bloqué retourne `422`.

### 3.3 Modifier volontairement son mot de passe

Un utilisateur déjà connecté peut décider de modifier son mot de passe depuis ses paramètres. Ce changement n’est pas imposé à la première connexion.

`POST /auth/modifier-mot-de-passe`

```json
{
  "mot_de_passe_actuel": "PasswordActuel123",
  "password": "NouveauPassword456",
  "password_confirmation": "NouveauPassword456"
}
```

Réponse `200` :

```json
{
  "message": "Votre mot de passe a été modifié avec succès. Votre session reste active.",
  "deconnexion_requise": false
}
```

La session et les tokens restent actifs. L’utilisateur décide lui-même quand appeler `/auth/deconnexion`. Après cette déconnexion volontaire, sa prochaine connexion exige l’OTP normalement.

La connexion unique sans OTP concerne uniquement le parcours « mot de passe oublié » après une réinitialisation réussie.

### 3.4 Profil authentifié

`GET /auth/profil` — authentification requise.

```json
{
  "utilisateur": {}
}
```

### 3.5 Déconnexion de l’appareil

`POST /auth/deconnexion` — authentification requise, corps vide.

Supprime uniquement le token courant.

```json
{ "message": "Déconnexion réussie." }
```

### 3.6 Déconnexion globale

`POST /auth/deconnexion-globale` — authentification requise, corps vide.

Supprime tous les tokens de l’utilisateur.

## 4. Mot de passe oublié

### 4.1 Demander le code

`POST /auth/mot-de-passe-oublie`

Limite : 3 requêtes par minute.

```json
{ "email": "utilisateur@exemple.ci" }
```

Réponse HTTP `200`, que l’adresse existe ou non :

```json
{
    "message": "Si un compte correspond à cette adresse, un code de réinitialisation a été envoyé."
}
```

L’utilisateur reçoit par e-mail un code à 6 chiffres, valable 10 minutes et limité à 5 essais.

### 4.2 Vérifier le code

`POST /auth/verifier-code-reinitialisation`

```json
{
  "email": "utilisateur@exemple.ci",
  "code_otp": "317098"
}
```

Réponse HTTP `200` :

```json
{
  "message": "Code vérifié. Vous pouvez maintenant définir un nouveau mot de passe.",
  "reset_autorise": true,
  "reset_token": "jeton_temporaire_de_64_caracteres",
  "expire_dans": 600
}
```

Le frontend doit conserver temporairement `reset_token` et afficher le formulaire de nouveau mot de passe uniquement après cette réponse.

### 4.3 Réinitialiser le mot de passe

`POST /auth/reinitialiser-mot-de-passe`

Limite : 5 requêtes par minute.

```json
{
  "reset_token": "jeton_temporaire_de_64_caracteres",
  "email": "utilisateur@exemple.ci",
  "password": "NouveauPassword123",
  "password_confirmation": "NouveauPassword123"
}
```

Le mot de passe doit contenir au moins 8 caractères, des lettres, majuscules/minuscules et chiffres. Après succès, tous les anciens tokens Sanctum sont supprimés.

## 5. Sidebar dynamique

`GET /navigation/sidebar` — authentification requise.

Les menus sont filtrés selon les permissions du rôle. Le rôle `ADMIN` reçoit tous les menus actifs et visibles.

```json
{
  "sidebar": [
    {
      "id": 1,
      "code": "ADMINISTRATION",
      "libelle": "Administration",
      "route": null,
      "route_active": null,
      "icone": "settings",
      "groupe": "Administration",
      "ordre": 10,
      "enfants": [
        {
          "id": 2,
          "code": "COMPTE_GERER",
          "libelle": "Gérer les comptes",
          "route": "/administration/comptes",
          "route_active": "/administration/comptes*",
          "icone": "users",
          "groupe": "Administration",
          "ordre": 10,
          "enfants": []
        }
      ]
    }
  ]
}
```

## 6. Comptes utilisateurs

Toutes les routes de cette section exigent la permission `COMPTE_GERER`. Le rôle `ADMIN` contourne automatiquement ce contrôle.

### 6.1 Liste

`GET /administration/comptes`

Query params :

| Paramètre | Type | Description |
|---|---|---|
| `recherche` | string | Nom, prénoms, email, matricule ou code |
| `statut` | string | Filtrer par statut |
| `id_role` | integer | Filtrer par rôle |
| `par_page` | integer | 1 à 100, défaut 15 |
| `page` | integer | Page demandée |

La réponse Laravel paginée contient `data`, `current_page`, `last_page`, `per_page`, `total`, `links`, etc.

### 6.2 Données du formulaire de création

`GET /administration/comptes/create`

Retourne `roles`, `civilites`, `statuts` et `valeurs_par_defaut`.

### 6.3 Créer un compte

`POST /administration/comptes`

```json
{
  "civilite_id": 1,
  "nom": "KOFFI",
  "prenoms": "Jean",
  "email": "jean.koffi@exemple.ci",
  "id_role": 2,
  "is_active": true,
  "statut": "Actif",
  "deux_fa_active": false
}
```

Champs requis : `civilite_id`, `nom`, `prenoms`, `email`, `id_role`.

`civilite_id` doit être un entier correspondant à une civilité existante. Il ne peut pas être `null` lors de la création.

Valeurs de `statut` : `Actif`, `Suspendu`, `Bloqué`, `Désactivé`.

Ne jamais envoyer `code`, `user_code` ou `user_id` :

- `code` est généré automatiquement (`USR-000001`, etc.) ;
- `user_code` reçoit le code de l’administrateur créateur ;
- `user_id` reçoit l’ID de l’administrateur créateur ;
- `created_by` reçoit l’ID de l’administrateur créateur ;
- le matricule au format `EBAC-0000-ANNEE` (exemple : `EBAC-0001-2026`) et le mot de passe temporaire sont générés automatiquement.

Pour envoyer une photo, utiliser `multipart/form-data`. Formats : JPG, JPEG, PNG, WEBP ; maximum 2 Mo.
La réponse contient `photo_url`, une URL absolue directement affichable par le frontend.

Réponse HTTP `201` :

```json
{
  "message": "Compte créé avec succès. Le mot de passe temporaire a été envoyé par email.",
  "compte": {}
}
```

### 6.4 Consulter et préparer l’édition

- `GET /administration/comptes/{id}` — retourne `compte`.
- `GET /administration/comptes/{id}/edit` — retourne `compte`, `roles`, `civilites`, `statuts`.

### 6.5 Modifier

- `PUT /administration/comptes/{id}`
- `PATCH /administration/comptes/{id}`
- `POST /administration/comptes/{id}` — utiliser cette méthode pour modifier avec une photo en `multipart/form-data`.

Tous les champs sont facultatifs. Champs acceptés : `civilite_id`, `nom`, `prenoms`, `email`, `photo`, `password`, `password_confirmation`, `id_role`, `is_active`, `statut`, `deux_fa_active`.

`code`, `user_code` et `user_id` sont interdits. Pour supprimer une photo existante, envoyer `photo: null`.

Pour la photo du profil connecté, envoyer également le formulaire en `POST multipart/form-data` vers `/administration/profil`.

### 6.6 Supprimer

`DELETE /administration/comptes/{id}`

Soft delete. Un administrateur ne peut pas supprimer son propre compte (`422`).

## 7. Profil utilisateur

Authentification requise, sans obligation de rôle administrateur.

- `GET /administration/profil` — profil courant.
- `GET /administration/profil/edit` — profil et champs modifiables.
- `PUT /administration/profil`
- `PATCH /administration/profil`

Champs acceptés : `civilite_id`, `nom`, `prenoms`, `email`, `photo`, `password`, `password_confirmation`, `mot_de_passe_actuel`.

Pour changer le mot de passe, `mot_de_passe_actuel` est obligatoire.

## 8. Rôles

Permission `ROLE_GERER` requise. Le rôle `ADMIN` dispose automatiquement de cet accès.

| Méthode | Route | Action |
|---|---|---|
| GET | `/administration/roles` | Liste paginée |
| POST | `/administration/roles` | Créer |
| GET | `/administration/roles/{id}` | Détails + permissions |
| PUT/PATCH | `/administration/roles/{id}` | Modifier |
| DELETE | `/administration/roles/{id}` | Supprimer |
| PUT | `/administration/roles/{id}/permissions` | Remplacer les permissions |
| GET | `/administration/roles/matrice-autorisations` | Matrice menus × actions disponible |
| GET | `/administration/roles/{id}/matrice-autorisations` | Matrice avec cases du rôle cochées |
| PUT | `/administration/roles/{id}/autorisations` | Remplacer les cases cochées |

Liste : query params `recherche`, `par_page`, `page`.

Création :

```json
{
  "code": "GESTIONNAIRE",
  "libelle": "Gestionnaire",
  "description": "Gestion des comptes",
  "actif": true,
  "permission_ids": [1, 2, 3],
  "autorisations": [
    {"menu_id": 1, "action_ids": [1, 3, 4]},
    {"menu_id": 2, "action_ids": [4]}
  ]
}
```

Le frontend doit envoyer `code`. Il est obligatoire, limité à 30 caractères, composé de lettres ASCII, chiffres, tirets ou underscores, et doit être unique. L’API le convertit automatiquement en majuscules.

Synchronisation des permissions :

```json
{ "permission_ids": [1, 3, 5] }
```

Envoyer un tableau vide retire toutes les permissions. Le rôle `ADMIN` et un rôle encore attribué à des comptes ne peuvent pas être supprimés.

La matrice reproduit l’écran « Module / Actions ». Chaque action envoyée doit d’abord être déclarée comme disponible sur le menu avec `action_ids` lors de la création ou modification du menu.

```json
{
  "autorisations": [
    {"menu_id": 1, "action_ids": [1, 2, 3]},
    {"menu_id": 2, "action_ids": [4]}
  ]
}
```

## 9. Permissions

Permission `PERMISSION_GERER` requise. Le rôle `ADMIN` dispose automatiquement de cet accès.

| Méthode | Route | Action |
|---|---|---|
| GET | `/administration/permissions` | Liste paginée |
| POST | `/administration/permissions` | Créer |
| GET | `/administration/permissions/{id}` | Détails + rôles |
| PUT/PATCH | `/administration/permissions/{id}` | Modifier |
| DELETE | `/administration/permissions/{id}` | Supprimer |

```json
{
  "libelle": "Voir les comptes",
  "description": "Autorise la consultation des comptes"
}
```

Le frontend ne doit jamais envoyer `code`. L’API génère automatiquement un code unique au format `PER-000001`, `PER-000002`, etc. Ce code ne peut pas être modifié.

Une permission encore attribuée à un rôle ne peut pas être supprimée.

## 10. Menus

Permission `MENU_GERER` requise. Le rôle `ADMIN` dispose automatiquement de cet accès.

| Méthode | Route | Action |
|---|---|---|
| GET | `/administration/menus` | Liste ordonnée |
| POST | `/administration/menus` | Créer |
| GET | `/administration/menus/{id}` | Détails, permissions et enfants |
| PUT/PATCH | `/administration/menus/{id}` | Modifier |
| DELETE | `/administration/menus/{id}` | Supprimer |

```json
{
  "id_parent": 1,
  "libelle": "Étudiants",
  "description": "Gestion des étudiants",
  "route": "/administration/etudiants",
  "route_active": "/administration/etudiants*",
  "icone": "users",
  "groupe": "Administration",
  "ordre": 50,
  "visible": true,
  "actif": true,
  "permission_ids": [1],
  "action_ids": [1, 2, 3, 4]
}
```

Le frontend ne doit jamais envoyer `code`. L’API génère automatiquement un code unique au format `MEN-000001`, `MEN-000002`, etc. Ce code ne peut pas être modifié.

`id_parent` peut être `null`. Un menu ne peut pas être son propre parent.

## 11. Actions

Permission `ACTION_GERER` requise. Le rôle `ADMIN` dispose automatiquement de cet accès.

Les actions initiales sont : `AJOUTER`, `SUPPRIMER`, `MODIFIER`, `VOIR`, `IMPRIMER` et `TELECHARGER`.

| Méthode | URL complète | Action |
|---|---|---|
| GET | `https://api-ebac.severinzran.ci/api/v1/administration/actions` | Lister les actions |
| POST | `https://api-ebac.severinzran.ci/api/v1/administration/actions` | Créer une action |
| GET | `https://api-ebac.severinzran.ci/api/v1/administration/actions/{id}` | Afficher une action |
| PUT/PATCH | `https://api-ebac.severinzran.ci/api/v1/administration/actions/{id}` | Modifier une action |
| DELETE | `https://api-ebac.severinzran.ci/api/v1/administration/actions/{id}` | Supprimer une action |
| PUT | `https://api-ebac.severinzran.ci/api/v1/administration/permissions/{permission_id}/actions` | Remplacer les actions d’une permission |

Création d’une action (le code est généré par l’API à partir du libellé) :

```json
{
  "libelle": "Valider",
  "description": "Valider un dossier",
  "actif": true
}
```

Attribution des actions à une permission :

```json
{
  "action_ids": [1, 3, 4]
}
```

Un tableau vide retire toutes les actions. Une action encore attribuée à une permission ne peut pas être supprimée.

## 11. Exemple client JavaScript

```javascript
const API_URL = "https://api-ebac.severinzran.ci/api/v1";

async function api(path, options = {}) {
  const token = localStorage.getItem("ebac_token");
  const response = await fetch(`${API_URL}${path}`, {
    ...options,
    headers: {
      Accept: "application/json",
      ...(options.body instanceof FormData ? {} : { "Content-Type": "application/json" }),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...options.headers,
    },
  });

  const data = await response.json().catch(() => null);
  if (response.status === 401) {
    localStorage.removeItem("ebac_token");
  }
  if (!response.ok) {
    const error = new Error(data?.message || "Erreur API");
    error.status = response.status;
    error.errors = data?.errors || {};
    throw error;
  }
  return data;
}

export async function demanderConnexion(email, password) {
  return api("/auth/connexion", {
    method: "POST",
    body: JSON.stringify({ email, password, nom_appareil: "Frontend EBAC" }),
  });
}

export async function confirmerOtp(idTentative, codeOtp) {
  const resultat = await api("/auth/confirmer-otp", {
    method: "POST",
    body: JSON.stringify({ id_tentative: idTentative, code_otp: codeOtp }),
  });
  localStorage.setItem("ebac_token", resultat.token);
  return resultat;
}
```

## 12. Contrôles effectués sur la production

Le 14 août 2026 :

- le domaine HTTPS répond ;
- le preflight CORS sur `/auth/connexion` retourne `204` ;
- `Access-Control-Allow-Origin: *` est présent ;
- `/navigation/sidebar` sans token retourne `401` ;
- `/auth/connexion` sans données retourne `422` avec les erreurs `email` et `password`.

## 13. Configuration de production recommandée

Le serveur doit utiliser au minimum :

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api-ebac.severinzran.ci
FRONTEND_URL=https://ebac.ci
```

Après modification :

```bash
php artisan optimize:clear
php artisan optimize
```

Ne jamais exposer Laravel Debugbar en production.
