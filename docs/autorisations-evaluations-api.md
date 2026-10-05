# Autoriser l'évaluation après une absence

Compte actif et Bearer Token requis. Rôles : `ADMIN`, `DIRECTION`, `SECRETARIAT`, `SECRETAIRE_ACADEMIQUE`.

1. `GET /api/v1/administration/autorisations-evaluations?autorisee=0` : absences non autorisées sur les séances réalisées avec présences validées. Pagination Laravel ; filtres `id_etudiant`, `id_seance`, `autorisee` (0 ou 1), `page`, `per_page` (1 à 100).
2. `GET /api/v1/administration/autorisations-evaluations/{id}` : détail d'une absence.
3. `POST /api/v1/administration/autorisations-evaluations/{id}/autoriser` avec `{"motif":"Justificatif accepté par la direction"}`.

`{id}` est l'identifiant de la ligne `presences`, retourné par la liste, et non l'identifiant de l'étudiant ou de la séance.
Le motif est obligatoire (5 000 caractères maximum). L'autorisation conserve l'auteur, la date et le motif ; une répétition conserve la première autorisation.
Réponse 200 : `message` et `absence`. Erreurs : 401 sans connexion, 403 si accès interdit, 404 si identifiant inconnu, 422 si motif invalide, étudiant présent ou présence non validée.

## Effet sur les notes

Les API enseignant par cours et par matière acceptent ensuite la note uniquement si toutes les absences des séances concernées sont autorisées. Les autres conditions de saisie restent applicables : séances réalisées, feuilles de présence validées et complètes, affectation de l'enseignant et feuille de notes en brouillon.

Chaque étudiant de la feuille de notes expose `evaluable`, `evaluation_autorisee` et `absences_non_autorisees` (identifiants des lignes `presences` encore bloquantes). L'étudiant reste marqué `statut_presence: absent` même après autorisation.
L'autorisation ne modifie ni les présences ni les cours à rattraper, et ne rouvre pas une feuille de notes déjà transmise. Autoriser avant la transmission si l'étudiant doit recevoir une note sur cette feuille.

## Installation

Exécuter `php artisan migrate` pour ajouter les colonnes d'autorisation aux présences.
