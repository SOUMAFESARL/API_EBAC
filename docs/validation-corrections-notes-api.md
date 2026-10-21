# Corrections des notes : traitement par le secr?tariat

1. L?enseignant soumet sa demande via `POST /api/v1/enseignant/corrections-notes` : id_seance, id_note, note_proposee, motif (ou format notes existant).
2. Le secr?tariat consulte la liste ou le d?tail, v?rifie ancienne note, note propos?e et motif.
3. Il applique directement la correction ou la rejette. La direction n?est pas requise.

Pr?fixe : `/api/v1/administration/corrections-notes`. Authentification Bearer, compte actif.

| M?thode | Route | Usage |
| --- | --- | --- |
| GET | `/` | Demandes pagin?es ; filtre statut=en_attente pour les demandes ? v?rifier |
| GET | `/{id}` | D?tail, ?tudiant, enseignant demandeur, mati?re, cours, promotion et historique |
| POST | `/{id}/appliquer` | V?rifier et appliquer directement la correction, sans corps |
| POST | `/{id}/rejeter` | Rejeter avec corps `{"motif":"Correction non justifi?e"}` |
| POST | `/{id}/valider` ou `/{id}/autoriser` | Autorisation seule, facultative ; ne change pas la note |

`/appliquer` est accessible ? ADMIN, SECRETARIAT et SECRETAIRE_ACADEMIQUE. Il accepte en_attente, autorisee et l?ancien statut transmise_direction. Si n?cessaire, autorisation et application sont enregistr?es ensemble, avec acteur, dates et traces. La note_initiale doit correspondre ? la note actuelle. La transaction est annul?e en cas d?erreur. Une correction appliqu?e ou rejet?e ne peut pas ?tre appliqu?e de nouveau.

La r?ponse d?application contient correction avec note_initiale, note_proposee, note_actuelle, note_finale, statut=appliquee, autorisee_par, appliquee_par, dates et actions actualis?es. Liste et d?tail contiennent actions.peut_appliquer d?s en_attente pour le secr?tariat ou ADMIN ; actions.peut_rejeter indique le rejet possible ; actions.peut_transmettre_direction reste faux.

L?ancienne route transmettre-direction reste disponible pour compatibilit?, mais elle est d?pr?ci?e dans Swagger et n?est pas n?cessaire. Les anciennes demandes transmises peuvent ?tre trait?es directement par le secr?tariat. L?application enseignant historique exige toujours une demande d?j? autoris?e.

Le circuit des feuilles de notes reste distinct : apr?s correction, la feuille revient au statut transmise pour v?rification. La correction ne r?g?n?re pas les bulletins.

Filtres : statut, id_note, id_seance, demande_par, search, page, per_page. Codes : 401 sans authentification, 403 r?le interdit, 404 introuvable, 422 transition impossible ou note initiale modifi?e.
