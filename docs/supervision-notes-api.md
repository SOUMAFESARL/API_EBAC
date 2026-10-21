# API de supervision des notes

Base : `/api/v1/administration/notes-transmises`. Authentification Bearer et compte actif requis.

- `GET /` : liste dans `feuilles_notes`, pagination dans `meta` (total, from, to, current_page, last_page, per_page).
- `GET /{id}` : panneau de d?tail dans `feuille_notes`, avec notes et historique.
- `POST /{id}/transmettre-direction` : bouton ? Contr?ler & transmettre ? du secr?tariat, sans corps. Une seule action effectue le contr?le et la transmission.
- `POST /{id}/rejeter-secretariat` : rejet du secr?tariat, corps `{"motif":"..."}` obligatoire.
- `POST /{id}/valider-direction` et `POST /{id}/rejeter-direction` : d?cision finale de la direction (motif obligatoire pour le rejet).

Chaque feuille contient mati?re, cours, promotion, enseignant, date_transmission, evaluation et evaluations. `evaluation` est null si la feuille contient plusieurs ?valuations ; les notes conservent alors leur propre ?valuation. Le regroupement existant reste une feuille par identifiant, sans fusion des ?valuations.

Le tableau et le panneau peuvent utiliser les m?mes champs :

| Champ | Affichage |
| --- | --- |
| notes_saisies / effectif | Saisies, par exemple 3 / 3 |
| moyenne_cours | Moyenne des notes renseign?es, null en absence de notes |
| statut_libelle | Contr?le secr?tariat, Transmission direction, Verrouill? ou rejet |
| verrouillee | Validation finale obtenue |
| etape_courante et etapes | Saisie enseignant, Contr?le secr?tariat, Transmission direction, Valid? & verrouill? |
| actions | Boutons autoris?s pour le r?le et le statut, d?sactiv?s si correction en cours |
| notes | Identifiant, ?valuation, note /20, ?tudiant avec matricule, nom et pr?noms |

`effectif` compte les inscrits distincts de la promotion pour l?ann?e de la feuille. `notes_saisies` compte les ?tudiants distincts ayant une note renseign?e ; une note de z?ro est renseign?e. `moyenne_cours` est une valeur num?rique : le front peut la formater en fran?ais avec deux d?cimales. Les dates sont ? formater par le front.

Recherche : `?search=Participation`, mati?re, cours, num?ro de promotion, nom ou pr?noms de l?enseignant. Filtres existants conserv?s : ann?e, promotion, mati?re, s?ance, statut. Pagination : page et per_page (1 ? 100). `statut_workflow` est stable ; `statut` conserve ses correspondances par r?le.

Les r?ponses des actions incluent les m?mes informations de pr?sentation actualis?es. Une feuille valid?e par la direction reste consultable ; toute correction passe par le circuit distinct des demandes de correction.
