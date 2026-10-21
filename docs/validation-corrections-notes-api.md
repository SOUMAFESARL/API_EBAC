# Validation des demandes de correction de notes

Authentification : `Authorization: Bearer <token>`, compte actif.

## Circuit

1. L?enseignant envoie sa demande par `POST /api/v1/enseignant/corrections-notes` (une note ou le tableau `notes` existant). La demande est imm?diatement soumise au statut `en_attente`.
2. Le secr?tariat (`SECRETARIAT`, `SECRETAIRE_ACADEMIQUE`) v?rifie et rejette avec un motif, ou transmet ? la direction : statut `transmise_direction`. Sa validation ne donne pas l?autorisation finale.
3. La direction autorise (`autorisee`) ou rejette (`rejetee`). Elle peut ?galement d?cider directement sur une demande `en_attente`. `ADMIN` peut effectuer ces d?cisions.
4. Apr?s autorisation, les routes existantes d?application permettent au secr?tariat, ? ADMIN ou ? l?enseignant demandeur d?appliquer la correction. La note change uniquement lors de cette application (`appliquee`). La feuille repart au contr?le (`transmise`). Les bulletins ne sont pas r?g?n?r?s automatiquement.

## Routes administration

Pr?fixe : `/api/v1/administration/corrections-notes`.

| M?thode | Route | Comportement |
| --- | --- | --- |
| GET | `/` | Liste pagin?e et filtr?e |
| GET | `/{id}` | D?tail et historique |
| POST | `/{id}/transmettre-direction` | Secr?tariat ou ADMIN : `en_attente` vers `transmise_direction` |
| POST | `/{id}/valider` | Secr?tariat : transmission ; direction ou ADMIN : autorisation finale |
| POST | `/{id}/autoriser` | Alias de `/valider`, m?mes r?gles |
| POST | `/{id}/rejeter` | Secr?tariat : uniquement `en_attente` ; direction ou ADMIN : `en_attente` ou `transmise_direction` |
| POST | `/{id}/appliquer` | Secr?tariat ou ADMIN : uniquement apr?s autorisation |

Transmission et validation : aucun corps requis. Rejet : corps obligatoire :

```json
{"motif": "La copie ne justifie pas la correction propos?e."}
```

Le motif doit ?tre non vide et limit? ? 5 000 caract?res. Il est conserv? dans l?historique sans remplacer le motif initial de l?enseignant.

Filtres : `statut` (`en_attente`, `transmise_direction`, `autorisee`, `appliquee`, `rejetee`), `id_note`, `id_seance`, `demande_par`, `search`, `page`, `per_page` (1 ? 100). Le filtre enseignant accepte ?galement le nouveau statut.

La liste renvoie la pagination Laravel (`data`, `total`, `current_page`, `last_page`). Chaque demande concerne une note. Les d?tails incluent le demandeur, l??tudiant, l??valuation, les notes initiale/propos?e/finale, la mati?re, le cours, la promotion et le niveau.

`actions` expose `peut_valider`, `peut_rejeter`, `peut_transmettre_direction`, `peut_appliquer` selon le r?le et le statut. Pour le secr?tariat, `peut_valider` correspond au contr?le suivi de la transmission. Apr?s transmission, l?enseignant ne peut plus modifier la demande et le secr?tariat ne peut plus la rejeter.

Les demandes en attente, transmises ? la direction ou autoris?es bloquent les doublons et la validation de leur feuille. Chaque transition est transactionnelle, verrouill?e et trac?e avec l?acteur et la date. Les transitions r?p?t?es sont refus?es.

Erreurs : `401` sans authentification, `403` pour un r?le interdit ou un compte inactif, `404` pour une demande inaccessible/introuvable, `422` pour un motif ou une transition invalide.

Aucune migration suppl?mentaire n?est n?cessaire : le statut existant est une cha?ne et l?historique conserve la transmission.
