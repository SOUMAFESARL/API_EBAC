# Déploiement automatique EBAC sur cPanel par FTP

Le workflow `.github/workflows/deploy.yml` se déclenche à chaque push sur `main` et peut aussi être lancé manuellement avec **Run workflow**.

## Secrets GitHub obligatoires

Dans le dépôt GitHub : **Settings → Secrets and variables → Actions → New repository secret**.

| Secret | Valeur attendue |
|---|---|
| `FTP_HOST` | Hôte FTP fourni par cPanel, sans `ftp://` |
| `FTP_USERNAME` | Nom d’utilisateur FTP cPanel |
| `FTP_PASSWORD` | Mot de passe FTP |
| `FTP_REMOTE_PATH` | Dossier distant terminé par `/` |

Exemple de chemin distant : `/api-ebac.severinzran.ci/`. La valeur exacte dépend de la racine attribuée au compte FTP dans cPanel.

## Préparation unique dans cPanel

Le dossier distant doit déjà contenir un fichier `.env` de production valide. Le workflow exclut ce fichier afin de ne jamais remplacer les identifiants de production.

Le dossier `storage` et le cache Laravel ne sont pas synchronisés afin de conserver les journaux, fichiers envoyés et permissions cPanel.

## Déroulement automatique

1. GitHub installe PHP 8.4 et Composer.
2. Les tests Laravel sont exécutés avec SQLite en mémoire.
3. Le déploiement est annulé si un test échoue.
4. GitHub réinstalle uniquement les dépendances Composer de production.
5. GitHub synchronise le projet et le dossier `vendor` avec cPanel par FTP.
6. `.env`, `storage`, les tests, les documents et les fichiers Git restent exclus.

## Limite du déploiement FTP

FTP peut envoyer des fichiers mais ne peut pas exécuter `php artisan`. Après une migration nouvelle, ouvrir **cPanel → Terminal** et exécuter :

```bash
cd /home/severinz/api-ebac.severinzran.ci
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Lancement manuel

Ouvrir l’onglet **Actions** du dépôt, sélectionner **Tests et déploiement EBAC**, puis **Run workflow**.

## Vérification

Après le déploiement, vérifier :

```text
https://api-ebac.severinzran.ci/api/v1/administration/actions
```

Sans token, une réponse `401` confirme que la route existe et qu’elle est protégée. Une réponse `404` signifie que la nouvelle version n’est pas encore déployée.
