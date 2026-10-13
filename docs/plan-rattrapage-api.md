# Plan de rattrapage

Accès par token Sanctum, compte actif avec rôle ADMIN, SECRETARIAT, SECRETAIRE_ACADEMIQUE ou DIRECTION.

Base : `/api/v1/administration/rattrapages`.

| Méthode | Route | Usage |
| --- | --- | --- |
| GET | `/plan` | Liste paginée et statistiques |
| GET | `/cours-a-rattraper/{id}/sessions` | Séances disponibles pour ce cours à faire |
| POST | `/inscrire` | Inscrire à une séance existante |
| PUT | `/{id}` | Reprogrammer une inscription, y compris après annulation |
| POST | `/{id}/annuler` | Annuler sans supprimer l'absence |

La liste accepte `search` (nom, prénoms, matricule, matière, cours), `id_promotion` (promotion d'origine), `id_etudiant`, `id_matiere`, `id_cours`, `id_seance`, `statut`, `page`, `per_page` (1 à 100). Chaque ligne représente une absence à une séance réalisée, issue d'une feuille de présence validée. Les séances sans cours sont aussi prises en charge pour les matières enseignées directement.

`statut_plan` : `non_planifie`, `inscrit`, `effectue`. `statistiques` fournit `non_planifies`, `inscrits`, `effectues`, `total`, `part_non_planifiee`. Le pourcentage est calculé sur les rattrapages restant à faire : non planifiés / (non planifiés + inscrits). Les statistiques suivent les filtres de contexte et la recherche, avant le filtre de statut et la pagination.

Les données incluent l'étudiant, la matière, le cours et son module, la séance manquée, sa promotion et son niveau, la programmation éventuelle et sa séance d'accueil avec promotion.

## Inscrire

Obtenir `id_cours_a_faire` dans `/plan`, puis choisir un identifiant dans `/cours-a-rattraper/{id}/sessions`.

```json
{
  "id_cours_a_faire": 1,
  "id_seance_rattrapage": 25,
  "observations": "Rattrapage avec la promotion suivante"
}
```

Envoyer ce corps à `POST /inscrire` (ou au POST existant `/rattrapages`). Date, horaires, enseignant et salle sont dérivés de la séance sélectionnée. Une séance annulée, réalisée, passée ou d'un autre enseignement est refusée. La promotion d'accueil peut différer de celle d'origine ; l'inscription de l'étudiant et sa présence initiale restent conservées. Une seule programmation existe par absence.

Pour reprogrammer : `PUT /{id}` avec `{"id_seance_rattrapage": 26}`. Ne pas envoyer `id_cours_a_faire`. Une programmation réalisée ne peut plus être modifiée.

Pour annuler : `POST /{id}/annuler` sans corps. La ligne retourne au statut `non_planifie`, mais la programmation annulée reste consultable. Une nouvelle séance peut ensuite être choisie par PUT.

Les endpoints historiques de programmation manuelle restent disponibles. `effectue` reflète un cours terminé ou une programmation marquée réalisée ; ce statut ne garantit pas qu'une note ait été saisie. La planification n'ajoute aucune note, ne change aucune moyenne et ne valide pas le cours. L'intégration de l'étudiant dans la saisie de présence et de notes de la promotion d'accueil nécessite un circuit distinct.

Déploiement : exécuter `php artisan migrate` pour ajouter la référence à la séance d'accueil. Swagger documente le parcours dans le groupe Rattrapages.
