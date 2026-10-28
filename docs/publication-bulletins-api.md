# Publication des bulletins

Authentification : `Authorization: Bearer <token>`. Compte actif requis.
Rôles autorisés : `ADMIN`, `DIRECTION`, `SECRETARIAT`, `SECRETAIRE_ACADEMIQUE`.

## Liste et détail

`GET /api/v1/administration/bulletins`

Filtres facultatifs : `id_inscription`, `id_promotion`, `id_annee_academique`,
`periode`, `statut` (`Brouillon`, `Généré` ou `Publié`), `page`, `per_page` (1 à 100).
La réponse contient la pagination Laravel et les inscriptions associées.

`GET /api/v1/administration/bulletins/{id}` retourne `bulletin`, ses lignes et matières.

## Publication

`POST /api/v1/administration/bulletins/{id}/publier`

Aucun corps requis. Publie un bulletin existant au statut `Brouillon` ou `Généré`, renseigne
`date_publication` avec la date courante et `updated_by` avec l'utilisateur connecté.
Un bulletin `Généré` est déjà consultable par son étudiant avant publication.
Une nouvelle publication du même bulletin conserve sa date et son auteur.
Cette action ne génère pas de bulletin ni ne recalcule ses notes.

## Génération après validation

La validation d'une feuille par le secrétariat ou la direction génère ou actualise
le bulletin `Annuel` de chaque étudiant inscrit concerné. Son statut vaut `Généré`
et `date_publication` reste vide. La simple transmission de notes non validées ne
les rend pas visibles. Les notes validées au secrétariat restent prises en compte
pendant leur transmission à la direction.

Les notes des feuilles rejetées ou retournées en correction sont retirées des
bulletins générés ; sans aucune note admissible, le bulletin généré est supprimé.
Une nouvelle validation le recrée. Un brouillon annuel existant est remplacé par
les lignes issues des notes validées lorsqu'elles deviennent disponibles.
Les bulletins publiés ou retirés ne sont pas modifiés automatiquement.

Les moyennes par matiere donnent le meme poids a chaque note ; la moyenne générale
utilise ceux des matières, sans arrondi intermédiaire. Cette génération enregistre
le bulletin en base ; le PDF est produit à la demande au téléchargement. Le rang reste absent sans classement.
La mention est calculée selon le barème décrit ci-dessous.
Les routes étudiant restent `GET /api/v1/etudiant/bulletins` et
`GET /api/v1/etudiant/bulletins/{id}`, avec le token du compte étudiant.

La génération est déclenchée par les prochaines transitions de validation : les
feuilles déjà validées avant cette modification ne sont pas reprises à la connexion.

Pour reprendre les anciennes validations d'un étudiant, exécuter sur le serveur :

```sh
php artisan bulletins:generer --etudiant=26
```

La commande se limite à cet étudiant et à ses inscriptions, utilise les mêmes règles
de validation que la génération automatique et peut être relancée sans doublon.
Elle ne publie aucun bulletin et conserve les documents déjà publiés ou retirés.
Sans inscription ou sans notes admissibles, elle ne crée pas de bulletin.

## Téléchargement par l'étudiant

Le détail `GET /api/v1/etudiant/bulletins/{id}` retourne aussi le profil complet
dans `etudiant` : identité et URL signée de la photo, civilité, naissance,
nationalité, coordonnées, situation familiale et professionnelle, statut,
date d'inscription, `eglise` et `dossier`. Les dates JSON sont au format
`YYYY-MM-DD` ; les relations absentes valent `null`. L'accès reste limité au
propriétaire du bulletin.

`GET /api/v1/etudiant/bulletins/{id}/telecharger`

Authentification `Authorization: Bearer <token_etudiant>`, compte actif et rôle
`ETUDIANT` requis. Un bulletin `Généré` est téléchargeable sans publication ; un
bulletin `Publié` avec date de publication est également téléchargeable.
Les brouillons, bulletins retirés et bulletins d'un autre étudiant retournent 404.

La liste et le détail exposent `telechargement_pdf_url`. Cette URL requiert aussi
le token : le frontend doit récupérer le fichier avec l'en-tête Authorization,
puis télécharger le Blob reçu. La réponse est un fichier binaire `application/pdf`,
avec `Content-Disposition: attachment; filename=bulletin-{id}.pdf` et
`Cache-Control: private, no-store`, et non un objet JSON.

Le PDF inclut les informations personnelles de l'étudiant : photo d'identité
intégrée depuis le stockage, civilité, nom, prénoms, matricule, date et lieu de
naissance, nationalité, e-mail, téléphone, adresse, situation professionnelle et
matrimoniale, nombre d'enfants, statut et date d'inscription. Il présente aussi le
numéro, le statut et la date d'ouverture du dossier, ainsi que l'église de
rattachement (nom, dénomination, région, district, ville/commune).

Le PDF reprend également les données du détail : année, niveau, promotion,
matières, notes, coefficients, appréciations, moyenne, rang, mention, UV et cours
à faire. Les valeurs absentes sont affichées avec un tiret. Un bulletin généré
porte la mention « non publié ». Aucune signature ou historique de versions n'est ajouté.

## Mentions et QR code

La mention générale et les mentions par matière suivent le barème : moins de 10
`À REPRENDRE`, de 10 à moins de 12 `Passable`, de 12 à moins de 14 `Assez bien`,
de 14 à moins de 16 `Bien`, à partir de 16 `Très bien`. Sans note : `À REPRENDRE`.
Une mention générale déjà enregistrée est conservée ; si elle est absente,
la réponse la calcule depuis la moyenne enregistrée, y compris pour les anciens
bulletins. Les prochaines générations enregistrent également cette mention.

Le détail retourne `bulletin.qr_code` :

```json
{
  "verification_url": "https://votre-serveur/api/v1/bulletins/1/verification?empreinte=...&signature=...",
  "image_data_url": "data:image/png;base64,..."
}
```

Le frontend peut afficher directement `image_data_url` dans une image. Le PDF
inclut ce QR et un lien cliquable. Aucun service QR externe n'est utilisé.
GD, déjà utilisé pour les images, est nécessaire à sa génération.

Le QR encode désormais un objet JSON contenant `matricule`, `niveau`,
`promotion` (numéro), `decision_fin_annee`, `moyenne_generale`, `site_web` et
`verification_url`. Cet objet est également retourné dans `qr_code.contenu`.
La décision est celle du bulletin, à défaut `decision_passage` de l'inscription,
sinon `null`. Le site est celui configuré pour l'école, à défaut
`https://www.ebac-edu.org`. Le scan affiche ces informations ; le lien de
vérification reste accessible dans ce contenu et sous le QR du PDF.
La taille d'impression reste 26 × 26 mm, avec une image source de 720 × 720 px.

La vérification `GET /api/v1/bulletins/{id}/verification` utilise l'URL signée
complète, sans token étudiant et sans expiration pour les documents imprimés.
Elle retourne `valide: true` et uniquement l'identifiant du bulletin, sa période,
son statut, sa date de publication, son année, sa moyenne et sa mention. Aucun
profil étudiant n'est exposé. Un bulletin `Généré` reste identifié comme non publié.
Signature absente ou falsifiée : 403 ; bulletin supprimé ou retiré : 404 ;
résultats modifiés depuis l'émission du QR : 410 (retélécharger le bulletin).

Réponse 200 : `{"message":"Bulletin publié.","bulletin":{...}}`.
Erreurs : 401 sans authentification, 403 si rôle interdit ou compte inactif,
404 si bulletin inexistant, 422 si statut incompatible ou filtre invalide.
