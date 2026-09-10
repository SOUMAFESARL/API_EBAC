# Créneaux hebdomadaires

Acc?s r?serv? aux r?les `ADMIN`, `SECRETAIRE_ACADEMIQUE` et `DIRECTION`, avec compte actif authentifi?. Tous les autres r?les re?oivent 403.

Base : `/api/v1/parametres/creneaux`. Authentification Bearer Sanctum et compte actif requis, comme les autres paramètres.

| Méthode | URL | Action |
| --- | --- | --- |
| GET | base?id_annee_academique=1 | Planning, tri par jour puis heure |
| GET | base/options?id_annee_academique=1 | Listes du formulaire |
| POST | base | Créer |
| GET | base/{id} | Détail |
| PUT | base/{id} | Modifier avec tous les champs obligatoires |
| PATCH | base/{id} | Modifier partiellement |
| DELETE | base/{id} | Supprimer logiquement |

```json
{
  "id_module_calendrier": 1,
  "id_niveau": 1,
  "id_matiere": 1,
  "id_cours": null,
  "id_promotion": null,
  "enseignant_id": 2,
  "id_salle": 1,
  "jour": 1,
  "heure_debut": "08:00",
  "heure_fin": "10:00"
}
```

Utiliser des identifiants existants, obtenus via `options`. `jour` : 1 lundi à 7 dimanche. Heures au format `HH:mm`, fin strictement après début, sans passage à minuit. Le module est un **module du calendrier académique**, pas un module de contenu pédagogique. L’année est déduite de ce module.

Le cours et la promotion sont facultatifs (`null`). La promotion est disponible pour le filtre « Toutes les promotions » ; en son absence, le créneau concerne le niveau. La matière doit être active et appartenir au niveau ; le cours doit être actif et appartenir à cette matière via son module pédagogique ; la promotion doit appartenir au niveau. Salle et enseignant doivent être actifs ; le compte enseignant doit avoir le rôle `ENSEIGNANT`.

Filtres de liste : `id_annee_academique` obligatoire, `id_module_calendrier`, `id_niveau`, `id_promotion` facultatifs. Réponse : `creneaux`, `nombre_creneaux`, `heures_hebdomadaires`. Ce dernier total additionne les durées des créneaux filtrés ; avec plusieurs modules, il ne représente pas nécessairement une seule semaine réelle.

Chaque créneau expose `duree_minutes` et les objets de présentation `module_calendrier`, `niveau`, `matiere`, `cours`, `promotion`, `enseignant`, `salle`. Seuls les identifiants et noms utiles de l’enseignant sont exposés. `options` accepte aussi `id_niveau` et `id_matiere` pour filtrer les listes dépendantes. Sans module déclaré, `modules_calendrier` est vide : désactiver le bouton Ajouter.

Les conflits sont refusés avec 422 et un message dans `errors.id_salle`, `errors.enseignant_id` ou `errors.id_niveau` identifiant le créneau concurrent. Le contrôle porte sur un même jour, un chevauchement strict des heures et au moins une occurrence commune de ce jour dans les périodes des modules, y compris entre années différentes. 08:00–10:00 puis 10:00–12:00 est autorisé. Les créneaux supprimés et les années supprimées ne bloquent plus de réservation. Une modification invalide conserve les anciennes données.

Les créneaux sont des modèles hebdomadaires : cette API ne génère pas les séances datées et ne soustrait pas encore jours fériés/congés aux occurrences. Les conflits récurrents restent donc contrôlés de façon conservatrice pendant ces périodes.

Un calendrier utilisé par des créneaux ne peut plus être remplacé ou supprimé : supprimer ou réaffecter d’abord ses créneaux (422). Cela évite que le remplacement complet des modules détruise les références du planning. Les anciennes références des créneaux supprimés peuvent devenir null après suppression des modules.

Migration : `php artisan migrate --path=database/migrations/2026_09_10_140000_create_creneaux_table.php`.
Tests : `php artisan test --filter=CreneauApiTest`. Swagger : `/api/documentation`, rubrique Créneaux.
