# Guide d’intégration — API Église

Ce document décrit uniquement l’intégration de l’API de gestion des églises.

## Informations générales

- URL de base : `/api/v1/eglises`
- Format des échanges : `application/json`
- Authentification : jeton Bearer Laravel Sanctum obligatoire
- Encodage : UTF-8

En-têtes recommandés :

```http
Accept: application/json
Content-Type: application/json
Authorization: Bearer <token>
```

Le compte connecté doit être actif. Une requête sans jeton valide retourne une réponse `401` :

```json
{
  "message": "Unauthenticated."
}
```

Un compte inactif ou suspendu reçoit une réponse `403` :

```json
{
  "message": "Ce compte est inactif ou ne peut pas accéder à cette ressource."
}
```

## Structure d’une église

| Champ | Type | Description |
|---|---|---|
| `id` | entier | Identifiant technique de l’église |
| `code` | chaîne | Code généré automatiquement, par exemple `EGL-000001` |
| `nom` | chaîne | Nom officiel de l’église |
| `sigle` | chaîne ou `null` | Sigle unique de l’église |
| `pasteur_principal` | chaîne ou `null` | Nom du pasteur principal |
| `denomination` | chaîne ou `null` | Dénomination religieuse |
| `adresse` | chaîne ou `null` | Adresse géographique |
| `region` | chaîne ou `null` | Région |
| `district` | chaîne ou `null` | District |
| `ville_commune` | chaîne | Ville ou commune |
| `telephone` | chaîne ou `null` | Numéro de téléphone |
| `email` | chaîne ou `null` | Adresse électronique |
| `statut` | chaîne | `Active`, `Suspendue` ou `Archivée` |
| `capacite_max_stagiaires` | entier | Nombre maximal de stagiaires, `0` par défaut |
| `representants` | tableau ou `null` | Liste des représentants de l’église |
| `observations` | chaîne ou `null` | Observations internes |
| `user_id` | entier ou `null` | Identifiant du compte associé à l’église |
| `user_code` | chaîne ou `null` | Copie automatique du code du compte associé |
| `created_by` | entier ou `null` | Utilisateur ayant créé l’église |
| `updated_by` | entier ou `null` | Dernier utilisateur ayant modifié l’église |
| `deleted_by` | entier ou `null` | Utilisateur ayant supprimé l’église |
| `created_at` | date ISO 8601 | Date de création |
| `updated_at` | date ISO 8601 | Date de dernière modification |
| `deleted_at` | date ISO 8601 ou `null` | Date de suppression logique |

Les champs `code`, `user_code`, `created_by`, `updated_by` et `deleted_by` sont contrôlés exclusivement par l’API. Le frontend ne doit jamais les envoyer.

## Structure d’un représentant

Chaque élément du tableau `representants` accepte les champs suivants :

| Champ | Obligatoire | Type | Limite |
|---|---:|---|---:|
| `nom` | oui | chaîne | 150 caractères |
| `prenoms` | non | chaîne ou `null` | 150 caractères |
| `fonction` | non | chaîne ou `null` | 100 caractères |
| `telephone` | non | chaîne ou `null` | 30 caractères |
| `email` | non | email ou `null` | 150 caractères |

Exemple :

```json
[
  {
    "nom": "Kouassi",
    "prenoms": "Jean",
    "fonction": "Secrétaire",
    "telephone": "+2250102030405",
    "email": "jean.kouassi@example.com"
  },
  {
    "nom": "Yao",
    "prenoms": "Marie",
    "fonction": "Trésorière",
    "telephone": null,
    "email": null
  }
]
```

## 1. Créer une église

```http
POST /api/v1/eglises
```

### Corps JSON

```json
{
  "nom": "Église Cité de la Grâce",
  "sigle": "ECG",
  "pasteur_principal": "Pasteur Yao Thomas",
  "denomination": "Église évangélique",
  "adresse": "Cocody Angré, 8e tranche",
  "region": "District autonome d’Abidjan",
  "district": "Abidjan Nord",
  "ville_commune": "Cocody",
  "telephone": "+2250102030405",
  "email": "contact@eglise.example",
  "statut": "Active",
  "capacite_max_stagiaires": 25,
  "representants": [
    {
      "nom": "Kouassi",
      "prenoms": "Jean",
      "fonction": "Secrétaire",
      "telephone": "+2250506070809",
      "email": "jean.kouassi@example.com"
    }
  ],
  "observations": "Église partenaire depuis 2026.",
  "user_id": 42
}
```

Champs obligatoires :

- `nom`
- `ville_commune`

Tous les autres champs sont facultatifs. Lorsque `statut` est omis, sa valeur est `Active`. Lorsque `capacite_max_stagiaires` est omis, sa valeur est `0`.

`user_id` est facultatif. Lorsqu’il est fourni, il doit correspondre à un compte existant. L’API copie automatiquement le champ `code` de ce compte dans `user_code`.

### Réponse `201 Created`

```json
{
  "message": "Église créée avec succès.",
  "eglise": {
    "id": 1,
    "code": "EGL-000001",
    "nom": "Église Cité de la Grâce",
    "sigle": "ECG",
    "pasteur_principal": "Pasteur Yao Thomas",
    "denomination": "Église évangélique",
    "adresse": "Cocody Angré, 8e tranche",
    "region": "District autonome d’Abidjan",
    "district": "Abidjan Nord",
    "ville_commune": "Cocody",
    "telephone": "+2250102030405",
    "email": "contact@eglise.example",
    "statut": "Active",
    "capacite_max_stagiaires": 25,
    "representants": [
      {
        "nom": "Kouassi",
        "prenoms": "Jean",
        "fonction": "Secrétaire",
        "telephone": "+2250506070809",
        "email": "jean.kouassi@example.com"
      }
    ],
    "observations": "Église partenaire depuis 2026.",
    "user_id": 42,
    "user_code": "USR-000042",
    "created_by": 5,
    "updated_by": null,
    "deleted_by": null,
    "created_at": "2026-08-18T12:00:00.000000Z",
    "updated_at": "2026-08-18T12:00:00.000000Z",
    "deleted_at": null
  }
}
```

Le code suit une séquence automatique : `EGL-000001`, `EGL-000002`, etc.

### Erreur de validation `422`

```json
{
  "message": "The nom field is required. (and 1 more error)",
  "errors": {
    "nom": [
      "The nom field is required."
    ],
    "ville_commune": [
      "The ville commune field is required."
    ]
  }
}
```

Exemple avec un sigle déjà utilisé :

```json
{
  "message": "The sigle has already been taken.",
  "errors": {
    "sigle": [
      "The sigle has already been taken."
    ]
  }
}
```

Exemple lorsqu’un champ technique est envoyé :

```json
{
  "message": "The code field is prohibited.",
  "errors": {
    "code": [
      "The code field is prohibited."
    ]
  }
}
```

## 2. Lister les églises

```http
GET /api/v1/eglises
```

### Paramètres de requête

| Paramètre | Type | Description |
|---|---|---|
| `page` | entier | Numéro de page, `1` par défaut |
| `par_page` | entier | Nombre de lignes par page, `15` par défaut, maximum `100` |
| `recherche` | chaîne | Recherche dans le nom, le sigle, le code et la ville/commune |
| `statut` | chaîne | Filtre exact sur le statut |

Exemple :

```http
GET /api/v1/eglises?recherche=grace&statut=Active&page=1&par_page=20
```

Les résultats sont triés par nom dans l’ordre alphabétique. Les églises supprimées logiquement ne sont pas retournées.

### Réponse `200 OK`

```json
{
  "current_page": 1,
  "data": [
    {
      "id": 1,
      "code": "EGL-000001",
      "nom": "Église Cité de la Grâce",
      "sigle": "ECG",
      "pasteur_principal": "Pasteur Yao Thomas",
      "denomination": "Église évangélique",
      "adresse": "Cocody Angré, 8e tranche",
      "region": "District autonome d’Abidjan",
      "district": "Abidjan Nord",
      "ville_commune": "Cocody",
      "telephone": "+2250102030405",
      "email": "contact@eglise.example",
      "statut": "Active",
      "capacite_max_stagiaires": 25,
      "representants": [],
      "observations": null,
      "user_id": 42,
      "user_code": "USR-000042",
      "created_by": 5,
      "updated_by": null,
      "deleted_by": null,
      "created_at": "2026-08-18T12:00:00.000000Z",
      "updated_at": "2026-08-18T12:00:00.000000Z",
      "deleted_at": null
    }
  ],
  "first_page_url": "https://api.example.com/api/v1/eglises?page=1",
  "from": 1,
  "last_page": 1,
  "last_page_url": "https://api.example.com/api/v1/eglises?page=1",
  "links": [
    {
      "url": null,
      "label": "&laquo; Previous",
      "active": false
    },
    {
      "url": "https://api.example.com/api/v1/eglises?page=1",
      "label": "1",
      "active": true
    },
    {
      "url": null,
      "label": "Next &raquo;",
      "active": false
    }
  ],
  "next_page_url": null,
  "path": "https://api.example.com/api/v1/eglises",
  "per_page": 20,
  "prev_page_url": null,
  "to": 1,
  "total": 1
}
```

Une liste vide retourne `200` avec `data: []` et `total: 0`.

## 3. Consulter une église

```http
GET /api/v1/eglises/{id}
```

Exemple :

```http
GET /api/v1/eglises/1
```

### Réponse `200 OK`

La propriété `compte` contient le compte associé lorsque `user_id` est renseigné.

```json
{
  "eglise": {
    "id": 1,
    "code": "EGL-000001",
    "nom": "Église Cité de la Grâce",
    "sigle": "ECG",
    "pasteur_principal": "Pasteur Yao Thomas",
    "ville_commune": "Cocody",
    "statut": "Active",
    "capacite_max_stagiaires": 25,
    "representants": [],
    "user_id": 42,
    "user_code": "USR-000042",
    "created_by": 5,
    "updated_by": null,
    "deleted_by": null,
    "created_at": "2026-08-18T12:00:00.000000Z",
    "updated_at": "2026-08-18T12:00:00.000000Z",
    "deleted_at": null,
    "compte": {
      "id": 42,
      "code": "USR-000042",
      "nom": "Compte",
      "prenoms": "Église",
      "email": "compte.eglise@example.com"
    }
  }
}
```

La réponse réelle contient également les autres propriétés de l’église et du compte utilisateur.

### Réponse `404 Not Found`

Une église inexistante ou déjà supprimée logiquement retourne une réponse `404`.

```json
{
  "message": "No query results for model [App\\Models\\Eglise] 999"
}
```

## 4. Modifier une église

```http
PUT /api/v1/eglises/{id}
PATCH /api/v1/eglises/{id}
```

`PATCH` est recommandé pour modifier seulement certains champs.

### Exemple de requête partielle

```json
{
  "pasteur_principal": "Pasteur Koffi Jean",
  "telephone": "+2250708091011",
  "statut": "Suspendue",
  "capacite_max_stagiaires": 30
}
```

### Réponse `200 OK`

```json
{
  "message": "Église modifiée avec succès.",
  "eglise": {
    "id": 1,
    "code": "EGL-000001",
    "nom": "Église Cité de la Grâce",
    "sigle": "ECG",
    "pasteur_principal": "Pasteur Koffi Jean",
    "ville_commune": "Cocody",
    "telephone": "+2250708091011",
    "statut": "Suspendue",
    "capacite_max_stagiaires": 30,
    "user_id": 42,
    "user_code": "USR-000042",
    "created_by": 5,
    "updated_by": 8,
    "deleted_by": null,
    "created_at": "2026-08-18T12:00:00.000000Z",
    "updated_at": "2026-08-18T14:30:00.000000Z",
    "deleted_at": null
  }
}
```

Pour dissocier le compte utilisateur de l’église :

```json
{
  "user_id": null
}
```

L’API place alors automatiquement `user_code` à `null`.

Pour remplacer toute la liste des représentants :

```json
{
  "representants": [
    {
      "nom": "N’Guessan",
      "prenoms": "Paul",
      "fonction": "Responsable académique"
    }
  ]
}
```

Pour vider la liste :

```json
{
  "representants": []
}
```

Les règles de validation de la création s’appliquent aussi à la modification. Le sigle actuel peut être renvoyé sans déclencher d’erreur d’unicité.

## 5. Supprimer une église

```http
DELETE /api/v1/eglises/{id}
```

### Réponse `200 OK`

```json
{
  "message": "Église supprimée avec succès."
}
```

La suppression est logique :

- la ligne reste conservée dans la base ;
- `deleted_at` reçoit la date de suppression ;
- `deleted_by` reçoit l’identifiant de l’utilisateur connecté ;
- l’église disparaît des listes et n’est plus accessible par la route de consultation standard.

Une seconde suppression du même identifiant retourne `404`.

## Récapitulatif des validations

| Champ | Règles principales |
|---|---|
| `nom` | obligatoire à la création, chaîne, maximum 180 caractères |
| `sigle` | facultatif, unique, maximum 30 caractères |
| `pasteur_principal` | facultatif, maximum 180 caractères |
| `denomination` | facultatif, maximum 180 caractères |
| `adresse` | facultatif, maximum 255 caractères |
| `region` | facultatif, maximum 120 caractères |
| `district` | facultatif, maximum 120 caractères |
| `ville_commune` | obligatoire à la création, maximum 120 caractères |
| `telephone` | facultatif, maximum 30 caractères |
| `email` | facultatif, email valide, maximum 150 caractères |
| `statut` | `Active`, `Suspendue` ou `Archivée` |
| `capacite_max_stagiaires` | entier compris entre 0 et 65535 |
| `representants` | tableau ou `null` |
| `user_id` | identifiant d’un compte existant ou `null` |

## Exemple JavaScript avec `fetch`

```javascript
const reponse = await fetch(`${API_URL}/api/v1/eglises`, {
  method: "POST",
  headers: {
    Accept: "application/json",
    "Content-Type": "application/json",
    Authorization: `Bearer ${token}`,
  },
  body: JSON.stringify({
    nom: "Église Cité de la Grâce",
    sigle: "ECG",
    pasteur_principal: "Pasteur Yao Thomas",
    ville_commune: "Cocody",
    statut: "Active",
    representants: [
      {
        nom: "Kouassi",
        prenoms: "Jean",
        fonction: "Secrétaire",
      },
    ],
  }),
});

const resultat = await reponse.json();

if (!reponse.ok) {
  console.error(resultat.errors ?? resultat.message);
} else {
  console.log(resultat.eglise);
}
```

## Exemple Axios

```javascript
const client = axios.create({
  baseURL: `${API_URL}/api/v1`,
  headers: {
    Accept: "application/json",
    Authorization: `Bearer ${token}`,
  },
});

const { data } = await client.get("/eglises", {
  params: {
    recherche: "grâce",
    statut: "Active",
    page: 1,
    par_page: 20,
  },
});

console.log(data.data);
```
