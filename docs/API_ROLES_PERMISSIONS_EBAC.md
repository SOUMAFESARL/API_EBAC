# API EBAC — rôles et permissions

Version : 17 août 2026
Base URL : `https://api-ebac.severinzran.ci/api/v1`

## 1. Authentification

Toutes les routes de ce document nécessitent un token Sanctum et la permission `ROLE_GERER` ou `PERMISSION_GERER` selon l’API.

```http
Authorization: Bearer VOTRE_TOKEN
Accept: application/json
Content-Type: application/json
```

Le rôle système `ADMIN` peut accéder à toutes les routes d’administration.

## 2. Principe de l’écran « Rôles & droits »

Le frontend affiche :

- un onglet pour chaque rôle actif ;
- le catalogue complet des permissions pour le rôle sélectionné ;
- une case cochée lorsque `accordee` vaut `true` ;
- une case décochée lorsque `accordee` vaut `false`.

Chaque clic sur une case envoie immédiatement une requête PATCH. Il n’est pas nécessaire d’ajouter un bouton « Enregistrer ».

## 3. Charger tous les rôles et leurs droits

### Requête

```http
GET https://api-ebac.severinzran.ci/api/v1/administration/roles/catalogue-droits
```

### Réponse HTTP 200

```json
{
  "nombre_roles": 2,
  "nombre_droits": 4,
  "roles": [
    {
      "id": 1,
      "code": "ADMIN",
      "libelle": "Administrateur",
      "description": "Administrateur de la plateforme",
      "actif": true,
      "droits": [
        {
          "id": 1,
          "code": "COMPTE_GERER",
          "libelle": "Gérer les comptes",
          "description": "Gérer les comptes",
          "accordee": true
        },
        {
          "id": 2,
          "code": "MENU_GERER",
          "libelle": "Gérer les menus",
          "description": "Gérer les menus",
          "accordee": true
        }
      ]
    }
  ]
}
```

## 4. Charger les droits d’un seul rôle

### Requête

```http
GET https://api-ebac.severinzran.ci/api/v1/administration/roles/{role_id}/droits
```

Exemple :

```http
GET https://api-ebac.severinzran.ci/api/v1/administration/roles/2/droits
```

### Réponse HTTP 200

```json
{
  "id": 2,
  "code": "GESTIONNAIRE",
  "libelle": "Gestionnaire",
  "description": "Gestion des opérations",
  "actif": true,
  "droits": [
    {
      "id": 1,
      "code": "COMPTE_GERER",
      "libelle": "Gérer les comptes",
      "description": "Gérer les comptes",
      "accordee": false
    }
  ]
}
```

## 5. Cocher ou décocher un droit

### URL

```http
PATCH https://api-ebac.severinzran.ci/api/v1/administration/roles/{role_id}/droits/{permission_id}
```

### Accorder le droit

```json
{
  "accordee": true
}
```

Réponse HTTP 200 :

```json
{
  "message": "Droit accordé au rôle.",
  "role_id": 2,
  "permission_id": 1,
  "accordee": true
}
```

### Retirer le droit

```json
{
  "accordee": false
}
```

Réponse HTTP 200 :

```json
{
  "message": "Droit retiré du rôle.",
  "role_id": 2,
  "permission_id": 1,
  "accordee": false
}
```

Le changement est enregistré immédiatement et utilisé dès la prochaine requête API protégée.

## 6. Créer un rôle

```http
POST https://api-ebac.severinzran.ci/api/v1/administration/roles
```

Le code du rôle est saisi par le frontend. Il est converti en majuscules et doit être unique.

```json
{
  "code": "GESTIONNAIRE",
  "libelle": "Gestionnaire",
  "description": "Gestion des opérations",
  "actif": true,
  "permission_ids": [1, 2]
}
```

Réponse HTTP 201 :

```json
{
  "message": "Rôle créé avec succès.",
  "role": {
    "id": 2,
    "code": "GESTIONNAIRE",
    "libelle": "Gestionnaire",
    "description": "Gestion des opérations",
    "actif": true,
    "permissions": []
  },
  "autorisations": []
}
```

## 7. Lister, consulter et modifier les rôles

| Méthode | URL | Description |
|---|---|---|
| GET | `/administration/roles` | Liste paginée ; paramètres `recherche`, `page`, `par_page` |
| GET | `/administration/roles/{id}` | Détail d’un rôle |
| PUT/PATCH | `/administration/roles/{id}` | Modifier code, libellé, description ou statut |
| DELETE | `/administration/roles/{id}` | Supprimer un rôle non utilisé |
| PUT | `/administration/roles/{id}/permissions` | Remplacer toutes les permissions du rôle |

Le rôle `ADMIN` ne peut pas être supprimé. Un rôle encore attribué à un compte ne peut pas être supprimé.

## 8. Créer une permission

```http
POST https://api-ebac.severinzran.ci/api/v1/administration/permissions
```

Le code de la permission est généré automatiquement par l’API. Le frontend ne doit jamais envoyer `code`.

```json
{
  "libelle": "Gérer les étudiants",
  "description": "Créer, consulter et modifier les étudiants"
}
```

Réponse HTTP 201 :

```json
{
  "message": "Permission créée avec succès.",
  "permission": {
    "id": 5,
    "code": "PER-000005",
    "libelle": "Gérer les étudiants",
    "description": "Créer, consulter et modifier les étudiants"
  }
}
```

## 9. Lister, consulter et modifier les permissions

| Méthode | URL | Description |
|---|---|---|
| GET | `/administration/permissions` | Liste paginée des permissions |
| GET | `/administration/permissions/{id}` | Permission avec rôles et actions |
| PUT/PATCH | `/administration/permissions/{id}` | Modifier libellé ou description |
| DELETE | `/administration/permissions/{id}` | Supprimer une permission non attribuée |
| PUT | `/administration/permissions/{id}/actions` | Remplacer les actions associées |

Payload pour associer les actions :

```json
{
  "action_ids": [1, 2, 3, 4]
}
```

## 10. Exemple JavaScript pour une case à cocher

```javascript
async function modifierDroit(roleId, permissionId, accordee, token) {
  const response = await fetch(
    `https://api-ebac.severinzran.ci/api/v1/administration/roles/${roleId}/droits/${permissionId}`,
    {
      method: 'PATCH',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        Authorization: `Bearer ${token}`,
      },
      body: JSON.stringify({ accordee }),
    },
  );

  const data = await response.json();
  if (!response.ok) throw data;
  return data;
}
```

## 11. Erreurs à gérer

### HTTP 401 — token absent ou invalide

```json
{"message":"Non authentifié."}
```

### HTTP 403 — permission insuffisante

```json
{
  "message": "Vous ne disposez pas de la permission nécessaire pour cette action.",
  "permissions_requises": ["ROLE_GERER"]
}
```

### HTTP 404 — rôle ou permission inexistant

```json
{"message":"No query results for model."}
```

### HTTP 422 — données invalides

```json
{
  "message": "Le champ accordee est obligatoire.",
  "errors": {
    "accordee": ["Le champ accordee est obligatoire."]
  }
}
```

## 12. Workflow frontend recommandé

1. Connecter l’administrateur et conserver le token retourné après validation OTP.
2. Appeler `GET /administration/roles/catalogue-droits`.
3. Créer un onglet avec chaque élément de `roles`.
4. Afficher les éléments de `role.droits` sous forme de cases à cocher.
5. Utiliser `droit.accordee` comme état initial de la case.
6. À chaque clic, appeler `PATCH /roles/{role_id}/droits/{permission_id}`.
7. En cas d’erreur, remettre la case dans son état précédent et afficher le message API.
