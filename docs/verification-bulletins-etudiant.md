# Vérification des bulletins étudiant — 22 octobre 2026

> État initial avant correction. Depuis, la validation génère un bulletin consultable
> sans publication. Voir [le fonctionnement actuel](publication-bulletins-api.md#génération-après-validation).

Comparaison du code API avec les trois captures fournies. Le code du frontend de ces écrans n'est pas présent dans ce dépôt ; leur branchement réel aux routes ne peut donc pas être confirmé ici.

## Accès après connexion

- `GET /api/v1/etudiant/bulletins` : liste paginée, filtre facultatif `id_annee_academique`.
- `GET /api/v1/etudiant/bulletins/{id}` : détail d'un bulletin enregistré. `{id}` désigne le bulletin, pas l'étudiant.
- Authentification Sanctum, compte actif et rôle `ETUDIANT` obligatoires.
- L'étudiant est résolu depuis `user_id` du compte connecté. Un bulletin d'un autre étudiant retourne 404.
- Seuls les bulletins au statut `Publié` avec `date_publication` sont exposés. Une connexion ne crée ni ne publie un bulletin.

## Correspondance avec les captures

| Élément affiché | État de l'API étudiant |
| --- | --- |
| Année, promotion, niveau, identité, matricule, photo | Présents |
| Moyenne générale, mention, rang | Présents, valeurs enregistrées du bulletin |
| Année en cours / clôturée | Présent, déduit de `date_fin`, sans clôture administrative explicite |
| Bulletin provisoire avant publication | Absent ; les brouillons restent masqués |
| Compteur « 4 matières évaluées sur 6 » | Absent de la liste |
| Matières, code, type, coefficient, note | Présents dans le détail, uniquement pour les lignes enregistrées |
| Mention par matière | Pas de champ dédié ; `appreciation` est renvoyée |
| Note complète / partielle et nombre de cours manquants | Absents ; le statut dépend seulement de la note et du seuil de validation |
| UV validées / en cours / à compléter | Partiel : `validees`, `non_validees`, `a_completer` ; aucun compteur `en_cours` |
| Cours à faire | Présents, filtrés par étudiant et année de la séance d'origine |
| Date et état du rattrapage | Absents du détail du bulletin ; une relation `rattrapage` existe dans l'API séparée `/api/v1/etudiant/cours-a-faire` |
| Rang « 5e / 24 » | Rang présent, effectif total absent |
| Appréciation générale | Absente ; `decision` n'est pas une appréciation générale |
| Versions v1/v2 et historique des corrections | Absents ; unicité actuelle sur inscription + période |
| QR de vérification et téléchargement PDF | Aucune route correspondante dans l'espace bulletins étudiant |

## Conclusion

L'accès aux bulletins publiés est implémenté, mais le contrat actuel ne suffit pas à alimenter intégralement les écrans fournis. Il faut notamment ajouter une consultation provisoire fondée sur les notes autorisées à être visibles, sans ouvrir l'accès aux brouillons administratifs, et définir les règles de complétude, de clôture et de versionnement avant d'étendre la publication officielle.

Le bulletin calculé `/api/v1/administration/bulletins-notes/{id}` est réservé à l'administration : il ne constitue pas une route utilisable par le frontend étudiant.
