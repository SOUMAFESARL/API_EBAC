from pathlib import Path
from xml.sax.saxutils import escape
from zipfile import ZipFile, ZIP_DEFLATED
import math
import xml.etree.ElementTree as ET

ROOT = Path(__file__).parent
# Estimations de charge restante en jours-personne, à affiner collectivement.
stories = []
def us(title, epic, role, want, value, criteria, api, front, route, state, refs, priority, api_days, front_days, deps='', decision=''):
    stories.append(dict(id=f'S5-US-{len(stories)+1:02}', title=title, epic=epic, role=role,
        story=f'En tant que {role}, je veux {want} afin de {value}.', criteria=criteria.split('|'),
        api=api, front=front, route=route, state=state, refs=refs, priority=priority,
        a=api_days, f=front_days, deps=deps, decision=decision))

us('Saisir les notes par matière','Notes','enseignant','saisir une note par étudiant et matière sans choisir un cours','évaluer ma promotion',
   'Année, promotion et matière obligatoires ; aucun cours requis.|Une note entre 0 et 20 est acceptée ; 0 est conservé et null efface le brouillon.|Les absents et étudiants hors promotion sont refusés par le serveur ; une requête invalide ne modifie aucune note.',
   'Intégrer la migration matière ; vérifier droits, validation et isolation des feuilles matière/cours ; conserver les routes historiques.',
   'Remplacer le sélecteur cours obligatoire par année/promotion/matière ; afficher num_promotion ; saisie décimale et erreurs par ligne.',
   'GET /enseignant/notes ; GET /enseignant/notes/feuille ; PUT /enseignant/notes','Existant à intégrer','02','P0',0.5,1.5,decision='D01;D03')
us('Expliquer les blocages de présence','Notes','enseignant','connaître les séances qui bloquent la saisie','compléter les présences nécessaires',
   'Aucune séance réalisée ou présence non validée ferme la saisie.|Toutes les séances de la matière dans le contexte sont prises en compte, même sans cours.|Le bandeau indique le motif et permet d’ouvrir la présence concernée ; après validation la feuille est rechargée.',
   'Exposer saisie_ouverte, seances_a_relever et motifs ; tester feuilles de présence incomplètes.',
   'Bandeau explicatif, champs désactivés et lien vers les présences ; gérer chargement et erreurs.',
   'GET /enseignant/notes/feuille ; API liste-presence existante','Existant à compléter','02','P0',0.5,0.5,'S5-US-01')
us('Transmettre une feuille complète','Notes','enseignant','transmettre mes notes au secrétariat','engager leur contrôle',
   'Au moins un étudiant évaluable et une note pour chacun sont exigés.|Une transmission répétée ou une modification après transmission est refusée.|Le statut et la date sont visibles ; une erreur conserve le brouillon sans transmission partielle.',
   'Tester atomicité, verrouillage et complétude ; documenter que transmise ne signifie pas validée officiellement.',
   'Bouton transmettre, récapitulatif et confirmation ; compteur des notes manquantes et statut serveur.',
   'POST /enseignant/notes/transmettre','Existant à intégrer','02','P0',0.5,0.5,'S5-US-01;S5-US-02')
us('Superviser les feuilles','Notes','secrétaire académique','consulter les feuilles et leurs anomalies','préparer le contrôle',
   'La liste filtre année, promotion, matière et statut et expose des compteurs cohérents.|Le détail distingue note manquante et absence non évaluable.|Les alertes d’écart indiquent la règle utilisée et ne modifient pas les notes.',
   'Créer liste paginée, détail enrichi et contrôles de cohérence ; calculer effectif évaluable et moyenne descriptive.',
   'Tableau de supervision, recherche, pagination et panneau détail avec alertes et étapes du circuit.',
   'PROPOSÉ : GET /administration/feuilles-notes ; GET /administration/feuilles-notes/{id}','À créer — route non repérée','19;20','P0',2,1.5,'S5-US-03','D02;D04')
us('Contrôler et transmettre à la direction','Notes','secrétaire académique','attester le contrôle puis transmettre une feuille','obtenir la validation officielle',
   'Seule une feuille transmise par l’enseignant peut être contrôlée.|Les anomalies bloquantes empêchent la transmission ; les avertissements sont distingués.|L’acteur, la date et la décision sont conservés ; aucune modification directe des notes.',
   'Ajouter transitions contrôlée et transmise_direction avec historique et contrôles serveur ; sécuriser les reprises.',
   'Actions contrôler/transmettre et affichage des refus ; rafraîchir le circuit après succès.',
   'PROPOSÉ : POST /administration/feuilles-notes/{id}/controler ; POST .../{id}/transmettre-direction','À créer — route non repérée','19;20','P0',2,1,'S5-US-04','D02;D04')
us('Valider officiellement les notes','Notes','membre de la direction','examiner puis valider une feuille contrôlée','publier des résultats fiables',
   'Seule une feuille transmise à la direction est validable.|La validation conserve acteur et date et verrouille les écritures ordinaires.|Le détail validé reste consultable ; toute correction suit son circuit dédié.',
   'Créer transition validée, droits direction et protections ; prévoir événement exploitable par les bulletins.',
   'Liste des feuilles à valider, détail, confirmation et état validé/verrouillé.',
   'PROPOSÉ : POST /administration/feuilles-notes/{id}/valider ; GET liste/détail','À créer — route non repérée','09;10;11','P0',2,1,'S5-US-05','D02')
us('Demander une correction par matière','Corrections','enseignant','demander la correction motivée d’une note de ma matière','rectifier une erreur sans contourner le verrouillage',
   'Le formulaire sélectionne matière, promotion et étudiant et envoie id_note sans cours obligatoire.|La note proposée diffère de l’actuelle, reste entre 0 et 20 et a au plus deux décimales ; motif obligatoire.|Une demande déjà en cours est refusée ; un enseignant ne peut demander pour une note hors de son périmètre.',
   'Ajouter routes enseignant et contrôle de propriété ; réutiliser le service de correction ; exposer options et contexte des notes.',
   'Modale de demande sans cours imposé, note actuelle en lecture seule, validation et confirmation.',
   'EXISTANT : POST /administration/corrections-notes ; PROPOSÉ : POST /enseignant/corrections-notes','Partiel — rôle enseignant refusé actuellement','03;04;21','P0',1.5,1,'S5-US-01;S5-US-06','D02;D05')
us('Suivre les demandes de correction','Corrections','enseignant ou secrétaire académique','consulter mes demandes et leur historique','suivre leur traitement',
   'Liste recherchable par étudiant/matière/promotion avec note initiale, proposée, demandeur et statut.|L’enseignant ne voit que son périmètre ; le secrétariat voit les demandes autorisées par son rôle.|Le détail restitue acteurs et dates ; le statut bulletin régénéré est absent tant que le document n’existe pas.',
   'Enrichir liste/détail avec étudiant, matière et num_promotion ; ajouter recherche et périmètre enseignant.',
   'Tableau, badges, compteurs et panneau chronologique ; états vide et erreur.',
   'GET /administration/corrections-notes[/{id}] ; PROPOSÉ : GET /enseignant/corrections-notes[/{id}]','Partiel — liste et traces existent','03;12;13;21','P0',1,1,'S5-US-07')
us('Autoriser ou rejeter une correction','Corrections','membre de la direction','décider d’une demande de correction','contrôler les changements de résultats',
   'Seules les demandes en attente peuvent être autorisées ou rejetées.|Le rejet exige un motif ; la décision est tracée.|La décision ne change pas la note ; les rôles non habilités reçoivent un refus serveur.',
   'Réutiliser autoriser/rejeter ; adapter la condition de feuille au circuit officiel et tester les permissions.',
   'Vue des demandes en attente et décisions ; boutons autoriser/refuser avec confirmation et motif.',
   'POST /administration/corrections-notes/{id}/autoriser ; POST .../{id}/rejeter','Existant à adapter','12;13','P0',0.5,1,'S5-US-08','D02')
us('Appliquer une correction autorisée','Corrections','secrétaire académique','appliquer une correction approuvée','mettre à jour la note avec une trace',
   'Seule une demande autorisée est applicable, une seule fois.|Si la note a changé depuis la demande, aucune écriture n’est appliquée.|Note, moyenne consultée et historique sont cohérents ; la feuille reste verrouillée.',
   'Réutiliser transaction existante ; tester notes matière et cours, concurrence et historique.',
   'Action appliquer avec comparaison avant/après ; rafraîchir détail et statut.',
   'POST /administration/corrections-notes/{id}/appliquer','Existant à intégrer','13;21','P0',0.5,0.5,'S5-US-09')
us('Régénérer le bulletin après correction','Bulletins','secrétaire académique','produire une nouvelle version du bulletin corrigé','préserver la fiabilité et les archives',
   'Une correction appliquée à un bulletin publié produit une nouvelle version sans écraser la précédente.|Un échec de génération est visible et peut être relancé sans doubler les versions.|Le lien entre correction et nouvelle version est consultable ; le badge régénéré suit le succès réel.',
   'Ajouter job de génération, versionnement, reprise et lien correction/bulletin.',
   'Afficher génération en cours, échec/reprise, version courante et accès aux archives.',
   'PROPOSÉ : POST /administration/corrections-notes/{id}/regenerer-bulletin','À créer — dépend du moteur de bulletins','03;13;21;26','P1',2,1,'S5-US-10;S5-US-26','D03')
us('Consulter la bibliothèque et le quota','Bibliothèque','enseignant','retrouver mes supports et mon espace disponible','organiser mes ressources',
   'Supports regroupés par matière/module/cours et filtrables par niveau, promotion et type.|Le quota compte toutes les versions, archives incluses ; restant et pourcentage concordent.|L’enseignant ne peut pas augmenter lui-même son quota.',
   'Créer catalogue sécurisé, agrégats de stockage et filtres ; préciser quota configuré par administration.',
   'Accordéons, filtres, jauge et compteurs ; états vide/chargement/erreur.',
   'PROPOSÉ : GET /enseignant/supports ; GET /enseignant/stockage','À créer — route non repérée','05;07','P1',1.5,1.5,decision='D06')
us('Déposer un support pédagogique','Bibliothèque','enseignant','déposer un fichier dans un enseignement affecté','le partager avec les étudiants concernés',
   'Matière/module/cours cohérents, titre, type et fichier requis ; note de version facultative.|Un fichier hors formats/taille admis ou dépassant le quota est refusé côté serveur.|Le dépôt crée la version 1 ; un étudiant non concerné ne peut télécharger le fichier.',
   'Créer upload privé, contrôles MIME/taille/quota et affectations ; garantir cohérence fichier/métadonnées.',
   'Panneau de dépôt avec sélecteurs dépendants, progression, erreurs et espace restant.',
   'PROPOSÉ : POST /enseignant/supports (multipart)','À créer — route non repérée','06','P1',2,1.5,'S5-US-12','D06')
us('Versionner et télécharger les supports','Bibliothèque','enseignant ou étudiant autorisé','accéder à la version pertinente d’un support','disposer de ressources traçables',
   'Une nouvelle version conserve les précédentes et incrémente la version.|Chaque téléchargement contrôle les droits, y compris sur les archives.|La liste affiche taille, date, type et note de version ; le quota est réévalué.',
   'Créer versions immuables et téléchargement protégé ; fournir accès étudiant selon inscription/enseignement.',
   'Historique, nouvelle version côté enseignant et téléchargement côté étudiant.',
   'PROPOSÉ : POST /enseignant/supports/{id}/versions ; GET /supports/{id}/versions/{version}/telecharger ; GET /etudiant/supports','À créer — route non repérée','05;07;24 (menu)','P1',2,1,'S5-US-13')
us('Voir mon profil professionnel','Personnel','enseignant','consulter mon identité professionnelle et mes affectations','vérifier mon dossier',
   'Identité, matricule interne, fonction, date d’entrée, contacts et qualifications sont visibles.|Les affectations actives affichent niveau, num_promotion et effectif sans doublons.|Le profil est en lecture seule et limité au compte connecté.',
   'Assembler dossier personnel et affectations ; réutiliser les relations existantes.',
   'Cartes identité/qualifications et liste des affectations ; masquer proprement les données absentes.',
   'PROPOSÉ : GET /enseignant/profil-professionnel','Partiel — affectations existantes, agrégation à créer','08','P2',1,0.5,'S5-US-16')
us('Ouvrir un dossier du personnel','Personnel','membre de la direction','créer un dossier personnel avec ou sans compte existant','centraliser les informations professionnelles',
   'Le parcours comporte compte, identité/contact, poste, contrat et pièces.|Le matricule est généré et unique ; le compte existant ne peut être lié à deux dossiers.|Créer le dossier ne crée aucun accès ; champs manquants et doublons sont signalés.',
   'Créer modèle dossier, validations, lien compte facultatif et stockage privé des pièces.',
   'Assistant cinq étapes, conservation des saisies au retour, récapitulatif et erreurs par étape.',
   'PROPOSÉ : POST /administration/personnel ; GET /administration/personnel/options','À créer — distinct de comptes','14;15','P2',2.5,2,'','D07')
us('Suivre les statuts du personnel','Personnel','membre de la direction','rechercher les dossiers et enregistrer congés ou départs','maintenir les affectations cohérentes',
   'Liste filtrable par fonction/statut avec compteurs en poste, enseignants et congés.|Un départ clôture le dossier sans le supprimer ; l’historique reste lisible.|Aucune nouvelle affectation n’est attribuable à une personne en congé ou partie.',
   'Créer transitions datées et contrôle lors des affectations ; définir traitement des affectations déjà actives.',
   'Tableau et compteurs, détail, confirmations de statut et historique.',
   'PROPOSÉ : GET /administration/personnel[/{id}] ; PATCH /administration/personnel/{id}/statut','À créer — route non repérée','14','P2',2,1,'S5-US-16','D07')
us('Préparer le passage de niveau','Progression','secrétaire académique','consulter le rapport de fin d’année d’une promotion','vérifier sa situation avant passage',
   'La promotion et l’année identifient un rapport avec niveau actuel/cible et situation par étudiant.|Les notes non validées et cours restant à faire sont explicités.|Un rapport absent produit un état explicatif et interdit de continuer, sans erreur générique.',
   'Créer rapport en lecture seule avec préconditions et identifiant/version du rapport.',
   'Assistant : sélection, vérification et rapport ; gérer le cas rapport introuvable observé.',
   'PROPOSÉ : GET /administration/passages-niveau/rapport','À créer — route non repérée','16','P1',2,1.5,'S5-US-06','D08')
us('Confirmer le passage collectif','Progression','secrétaire académique','faire passer une promotion au niveau suivant','ouvrir l’année suivante sans perdre les cours dus',
   'Confirmation explicite sur un rapport à jour ; un double clic ne double pas le passage.|Toute la promotion avance sans redoublement selon la règle des captures ; les cours dus sont conservés.|Le passage est atomique, tracé, consultable et ne dépasse pas le dernier niveau du cursus.',
   'Créer opération idempotente et transaction, mise à jour du niveau et report des obligations.',
   'Étapes confirmation/mise à jour/rattrapage ; désactiver pendant traitement et afficher le résultat.',
   'PROPOSÉ : POST /administration/passages-niveau','À créer — route non repérée','16','P1',2.5,1,'S5-US-18','D08')
us('Planifier les rattrapages','Rattrapages','secrétaire académique','inscrire un étudiant à une session d’un cours dû','organiser son rattrapage',
   'Liste des cours dus avec filtres, statut et compteurs calculés sur une population définie.|La session concerne le même cours ; l’étudiant garde sa promotion d’origine.|Inscription/annulation sont tracées ; aucun auto-enregistrement étudiant ; annulation refusée si résultat finalisé.',
   'Créer obligations, sessions admissibles et inscriptions ; contrôler doublons et statut.',
   'Tableau et indicateurs ; modale de session et confirmation d’annulation.',
   'PROPOSÉ : GET /administration/rattrapages ; POST .../{id}/inscrire ; POST .../{id}/annuler','À créer — route non repérée','17;18','P1',2.5,1.5,'S5-US-19','D03;D09')
us('Intégrer les résultats de rattrapage','Rattrapages','étudiant','voir mon cours dû régularisé après évaluation','suivre ma progression réelle',
   'L’inscription seule ne valide pas le cours ; présence et note sont requises.|La note est reprise sans pénalité ; sans note le cours est exclu du calcul prévu.|Les feuilles de la session hôte acceptent les inscrits en rattrapage sans changer leur promotion d’origine.',
   'Adapter éligibilité notes/présences et rattachement des résultats ; recalculer obligations et progression.',
   'Afficher cours dus, session et résultat ; aucun bouton d’auto-inscription.',
   'PROPOSÉ : GET /etudiant/cours-a-faire ; adaptation API notes/présences','À créer — intégration transversale','17;18;27','P1',3,1,'S5-US-20;S5-US-01','D03;D09')
us('Gérer le référentiel des épreuves','Soutenances','secrétaire académique','définir les épreuves et leurs coefficients','préparer les nouvelles sessions',
   'Libellé, rang et coefficient positif sont requis ; liste et somme des coefficients concordent.|Ajout, modification et retrait sont possibles selon les droits.|Une modification du référentiel ne change pas rétroactivement les sessions existantes.',
   'Créer référentiel et copie des épreuves/coefficients à l’ouverture des sessions.',
   'Tableau, recherche, formulaire ajout/modification et confirmation de retrait.',
   'PROPOSÉ : GET/POST /administration/epreuves-soutenance ; PATCH/DELETE .../{id}','À créer — route non repérée','23','P2',1.5,1)
us('Ouvrir une session et contrôler les candidats','Soutenances','secrétaire académique','ouvrir une session et consulter les candidats éligibles','organiser la fin de cycle',
   'Une session copie le référentiel et se rattache à une promotion/année.|Éligibilité calculée côté serveur : matières académiques requises, stage et frais de soutenance.|Les causes de non-éligibilité sont visibles ; sans session sélectionnée les actions de programmation sont indisponibles.',
   'Créer sessions, candidats et service d’éligibilité connecté aux résultats, stages et frais.',
   'Liste candidats, filtre de session, indicateurs et formulaire d’ouverture.',
   'PROPOSÉ : GET/POST /administration/sessions-soutenance ; GET .../{id}/candidats','À créer — dépendances stages/finances à vérifier','22;29','P2',3,1.5,'S5-US-22;S5-US-06','D10')
us('Programmer et noter les soutenances','Soutenances','secrétaire académique','programmer les épreuves puis porter les notes du jury','préparer une délibération complète',
   'Chaque candidat éligible dispose d’un planning par épreuve ; conflits de créneau signalés.|Notes entre 0 et 20 et moyenne pondérée selon les coefficients de la session.|La transmission exige toutes les notes attendues ; les notes restent provisoires avant décision direction.',
   'Créer planning, notes par épreuve, calcul et transmission atomique.',
   'Planning et grille de notes par épreuve ; progression x/n et erreurs explicites.',
   'PROPOSÉ : PUT /administration/sessions-soutenance/{id}/planning ; PUT .../{id}/notes ; POST .../{id}/transmettre','À créer — écran de saisie non fourni','22;23','P2',3,2,'S5-US-23','D10')
us('Valider et consulter la soutenance','Soutenances','direction et étudiant','valider les résultats puis consulter la soutenance autorisée','finaliser le parcours de fin de cycle',
   'La direction valide uniquement une délibération complète et transmise ; décision datée.|L’étudiant ne voit que son dossier et les résultats publiés.|Sans session, les épreuves et prérequis sont expliqués ; l’obtention du diplôme tient compte de tout le cursus.',
   'Créer validation/publication, vue étudiant et contribution de la matière Soutenance à la progression.',
   'Vue direction à définir et écran Ma soutenance avec états sans session, programmé et publié.',
   'PROPOSÉ : POST /administration/sessions-soutenance/{id}/valider ; GET /etudiant/soutenance','À créer — vue direction seulement évoquée au menu','22;29','P2',2,1.5,'S5-US-24','D10')
us('Consulter et télécharger les bulletins','Bulletins','étudiant','consulter mes bulletins annuels et leurs versions','connaître mes résultats officiels',
   'Les bulletins sont classés par année avec matières, coefficients, mentions et cours à faire.|L’aperçu provisoire est identifié ; téléchargement officiel seulement après clôture et validation requise.|PDF et QR identifient la bonne version ; un étudiant ne peut lire le bulletin d’un autre.',
   'Créer moteur de calcul, instantanés, PDF privé, versions et vérification QR à données minimales.',
   'Liste annuelle, aperçu fidèle, badges provisoire/publié, téléchargement et archives.',
   'PROPOSÉ : GET /etudiant/bulletins[/{id}] ; GET .../{id}/pdf ; GET /bulletins/verifier/{token}','À créer — route non repérée','26;27','P1',4,2,'S5-US-06','D03;D11')
us('Confirmer mon inscription annuelle','Inscription','étudiant','compléter mon dossier et confirmer mon inscription','ouvrir mes services de l’année',
   'Le parcours affiche dossier, frais appelés, confirmation et règlement.|Les pièces requises sont vérifiées côté serveur ; un dossier incomplet ne peut être confirmé.|La confirmation ouvre les services autorisés sans exiger le règlement immédiat ; statut de paiement séparé.',
   'Réutiliser dossier étudiant ; créer confirmation annuelle idempotente et liste des pièces manquantes.',
   'Assistant quatre étapes et lien compléter mon dossier ; afficher les frais sans simuler un paiement.',
   'EXISTANT : GET /etudiant/dossier ; PROPOSÉ : GET /etudiant/inscription ; POST .../confirmer','Partiel — dossier existant, confirmation à créer','24;25','P1',2,1.5,decision='D12')
us('Consulter ma progression','Progression','étudiant','voir mon avancement académique','identifier les exigences restant à satisfaire',
   'Avant confirmation de l’inscription, état verrouillé avec lien vers la confirmation.|Après confirmation, résultats validés, éléments partiels et obligations sont distingués.|Le taux ne traite pas une donnée absente comme une réussite ; stage et soutenance sont pris en compte selon le cursus.',
   'Créer agrégation progression et contrôle d’accès lié à l’inscription.',
   'État verrouillé observé ; vue de progression ouverte à spécifier avec matières et éléments restants.',
   'PROPOSÉ : GET /etudiant/progression','À créer — vue ouverte non fournie','25;29','P1',2,1,'S5-US-27;S5-US-06','D03;D10')
us('Administrer les accès utilisateur','Comptes','administrateur habilité','consulter et gérer l’état d’un compte','maîtriser les accès au système',
   'La fiche expose identité, rôle, état et second facteur sans secret.|L’adresse de connexion reste non modifiable selon la capture ; vérifier la règle serveur.|Suspension/désactivation empêchent les accès ; la suppression préserve les références métier et l’audit.',
   'Auditer routes comptes et politique d’état ; adapter permissions et révocation des sessions ; définir suppression logique.',
   'Fiche compte et menu conditionné aux droits ; confirmations et motifs des actions sensibles.',
   'EXISTANT : GET/PATCH/DELETE /administration/comptes/{compte} ; transitions à préciser','Partiel — routes comptes existantes','28','P2',1.5,1,decision='D13')
us('Recetter le circuit notes de bout en bout','Qualité','responsable produit','vérifier le circuit sur les quatre rôles','livrer un parcours cohérent',
   'Scénario matière sans cours : saisie → transmission → contrôle → validation → correction autorisée → application.|Tests négatifs : rôle interdit, autre promotion, notes invalides, double clic et conflit de mise à jour.|Swagger, erreurs UI et statuts concordent ; aucune donnée fictive présentée comme réelle.',
   'Tests intégration, migration et non-régression notes par cours ; contrats OpenAPI et données de recette anonymisées.',
   'Tests E2E des quatre rôles, clavier, formulaires et petits écrans ; recette des états vide/erreur.',
   'Ensemble des routes P0','À planifier — qualité transverse','02;03;04;09;10;11;12;13;19;20;21','P0',1,1,'S5-US-01 à S5-US-10')

def ids(s): return [f'CA-{s["id"]}-{i+1}' for i in range(len(s['criteria']))]

sheets=[]
def sheet(name, headers, rows, widths): sheets.append((name, headers, rows, widths))
core=[s for s in stories if s['priority']=='P0']
sum_a=sum(s['a'] for s in core); sum_f=sum(s['f'] for s in core)
sheet('Sprint 5', ['Rubrique','Définition / proposition'], [
 ['Titre','Sprint 5 — Évaluation académique, validation et services associés'],
 ['Objectif du socle','Rendre opérationnel le circuit des notes par matière : enseignant → secrétariat → direction → correction tracée.'],
 ['Nature du document','Backlog proposé à partir des 29 captures et du code API local. Ce classeur définit le travail ; il ne certifie ni le déploiement API ni une intégration frontend existante.'],
 ['Périmètre P0',f'{len(core)} stories : S5-US-01 à S5-US-10 et S5-US-30. Priorité au circuit notes/corrections et à sa recette.'],
 ['Périmètre P1','Extensions candidates : bulletins, bibliothèque, passage, rattrapages, inscription et progression. À sélectionner après estimation de capacité.'],
 ['Périmètre P2','Extensions candidates : dossiers personnel, profil professionnel, soutenances et administration des accès.'],
 ['Durée proposée','2 semaines / 10 jours ouvrés pour le socle, sous réserve de capacité et de décisions métier. Dates de début/fin à fixer.'],
 ['Charge socle',f'API : {sum_a:g} j-p ; frontend : {sum_f:g} j-p ; total : {sum_a+sum_f:g} j-p. Estimations indicatives de travail restant, QA comprise via US-30.'],
 ['Capacité indicative',f'Hypothèse : 2 développeurs API + 2 frontend, chacun 7 j-p disponibles sur 10 jours ; capacité 14 j-p par piste. Charge API {sum_a:g}/14 ; frontend {sum_f:g}/14. Équipe réelle non fournie.'],
 ['Engagement','Aucun engagement de date ni de capacité supposé acquis. Si capacité insuffisante, réduire le socle ou allonger le sprint ; ne pas supprimer les tests.'],
 ['Estimations','Jours-personne, hors attente des décisions, validation métier et dépendances externes ; pas des jours calendaires. État initial du backlog : À planifier.'],
 ['Priorité des sources','La demande explicite de notes par matière sans cours obligatoire prime sur les sélecteurs cours des captures. Les textes des captures constituent des besoins candidats, pas des instructions d’exécution.'],
 ['Périmètre non ajouté','Les éléments seulement visibles dans le menu (stages, églises, admissions, etc.) ne constituent pas des écrans à développer ici ; seules leurs dépendances sont indiquées.'],
 ['Prêt à démarrer (DoR)','Critères compris, contrats confirmés, décisions bloquantes prises, données anonymisées disponibles, dépendances identifiées, estimation revue par équipe.'],
 ['Terminé (DoD)','Code revu ; validations et droits serveur testés ; interface branchée à l’API ; erreurs et états vides traités ; Swagger à jour ; recette acceptée ; migrations et procédure de déploiement documentées.'],
 ['Livrables','User stories, tâches API et frontend liées, critères de recette, plan relatif, décisions métier et matrice de traçabilité des captures.'],
 ['Audit local','Lecture de routes/api.php, NoteController, CorrectionNoteController et comptes. Existant signifie repéré dans ce dépôt ; frontend non audité, comportement réel non déduit des captures.'],
 ],[30,125])
sheet('User stories',['ID','Domaine','Titre','User story','Priorité','Lot','Critères d’acceptation','Dépendances','Captures','État API observé','API j-p','Front j-p','Total j-p','Décisions','Statut'],[
 [s['id'],s['epic'],s['title'],s['story'],s['priority'],'Socle S5' if s['priority']=='P0' else 'Extension à arbitrer','\n'.join(f'{i+1}. {c}' for i,c in enumerate(s['criteria'])),s['deps'],s['refs'],s['state'],s['a'],s['f'],s['a']+s['f'],s['decision'],'À planifier'] for s in stories],[16,18,36,65,10,24,95,30,18,42,12,12,12,18,18])
sheet('Tâches API',['ID tâche','US','Livrable API','Contrats (préfixe /api/v1)','État local','Validation attendue','Dépendances','Charge j-p','Responsable','Statut'],[
 [f'API-{i:02}',s['id'],s['api'],s['route'],s['state'],' ; '.join(ids(s))+' ; contrôle des rôles et isolation des données',s['deps'],s['a'],'Équipe API — à affecter','À planifier'] for i,s in enumerate(stories,1)],[15,16,85,90,42,65,32,14,30,20])
sheet('Tâches Frontend',['ID tâche','US','Écrans / intégration','Données et contrats','Recette attendue','Dépendances','Charge j-p','Responsable','Statut'],[
 [f'FRONT-{i:02}',s['id'],s['front'],s['route'],' ; '.join(ids(s))+' ; chargement, vide, erreur et clavier',f'API-{i:02} ; '+s['deps'],s['f'],'Équipe frontend — à affecter','À planifier'] for i,s in enumerate(stories,1)],[15,16,95,90,65,40,14,30,20])
sheet('Recette',['ID critère','US','Acteur','Résultat attendu / scénario','Type','Résultat','Preuve / anomalie'],[
 [cid,s['id'],s['role'],c,'Fonctionnel / intégration','Non exécuté',''] for s in stories for cid,c in zip(ids(s),s['criteria'])],[25,16,30,125,28,20,35])
sheet('Plan et charges',['Phase / lot','Fenêtre proposée','Stories','API j-p','Front j-p','But / condition'],[
 ['Cadrage','J1','Décisions D01–D05 ; contrats P0',0,0,'Inclus dans les tâches ; cadrer la machine d’états et le droit enseignant avant dépendances.'],
 ['Saisie et présence','J1–J3','S5-US-01 à 03',sum(s['a'] for s in stories[:3]),sum(s['f'] for s in stories[:3]),'Intégrer l’existant ; brancher le frontend et préparer données de recette.'],
 ['Contrôle et validation','J2–J6','S5-US-04 à 06',sum(s['a'] for s in stories[3:6]),sum(s['f'] for s in stories[3:6]),'API et frontend peuvent avancer sur contrats convenus ; intégrer ensuite.'],
 ['Corrections','J4–J8','S5-US-07 à 10',sum(s['a'] for s in stories[6:10]),sum(s['f'] for s in stories[6:10]),'Prérequis : statuts officiels et périmètre enseignant stabilisés.'],
 ['Intégration et recette','J9–J10','S5-US-30',stories[-1]['a'],stories[-1]['f'],'Recette métier, régressions, documentation et préparation livraison.'],
 ['TOTAL SOCLE','10 jours proposés','P0',sum_a,sum_f,'Charge totale ; ne pas additionner avec les lignes de détail ci-dessus.'],
 ['EXTENSIONS P1','Non datées','Toutes les stories P1',sum(s['a'] for s in stories if s['priority']=='P1'),sum(s['f'] for s in stories if s['priority']=='P1'),'Non engagées dans les 10 jours ; sélectionner selon capacité et dépendances.'],
 ['EXTENSIONS P2','Non datées','Toutes les stories P2',sum(s['a'] for s in stories if s['priority']=='P2'),sum(s['f'] for s in stories if s['priority']=='P2'),'Non engagées dans les 10 jours ; sélectionner selon capacité et dépendances.'],
 ['TOTAL BACKLOG','À replanifier si tout retenu','P0 + P1 + P2',sum(s['a'] for s in stories),sum(s['f'] for s in stories),'Ne représente pas une promesse de livraison dans un seul sprint.'],
 ],[28,25,40,15,15,100])

decisions=[
 ('D01','Notes par matière','Confirmé par utilisateur','La matière remplace le cours obligatoire pour la saisie ; conserver compatibilité des routes cours.','S5-US-01','Acté'),
 ('D02','Machine d’états et corrections','Écart API / captures','API actuelle : brouillon/transmise, correction permise sur transmise. Cible proposée : brouillon → transmise (secrétariat) → contrôlée → transmise_direction → validée. Décider migration des anciennes feuilles et si corrections réservées aux validées.','US-03 à 10','À décider avant développement'),
 ('D03','Notes matière et cours coexistantes','Règle à définir','Ne pas compter deux fois une matière. Proposition : mode d’évaluation explicite par contexte, note directe OU moyenne des cours. Définir effet des rattrapages cours sur une matière notée directement.','US-01;11;20;21;26;28','Bloquant calculs officiels'),
 ('D04','Feuille incomplète et alertes','Contradiction de capture','Capture 20 : 3/4 notes en contrôle et bouton transmettre ; API refuse transmission incomplète. Proposition : conserver blocage des notes manquantes évaluables ; seuil d’écart à la moyenne à définir, sans inventer 5 points.','US-04;05','À décider'),
 ('D05','Corrections enseignant','Écart de droits','Capture enseignant permet demande et suivi ; API administration refuse ENSEIGNANT. Créer accès limité à ses notes ; aucun droit enseignant d’autoriser ou d’appliquer.','US-07;08','Cible proposée'),
 ('D06','Supports et quota','Paramètres de maquette','600 Mo et les nombres affichés sont des exemples, pas des constantes. Confirmer formats/taille maximale, quota, gestion des archives et politique d’accès étudiant.','US-12 à 14','À paramétrer'),
 ('D07','Dossier personnel','Écrans partiels','Étapes identité/poste/contrat/pièces non détaillées dans les captures. Définir champs requis, effets congé/départ sur les affectations existantes et accès compte.','US-15 à 17','À préciser'),
 ('D08','Passage annuel','Règle issue des captures','Pas de redoublement ; conserver les obligations. Définir préconditions de clôture, dernier niveau et traitement des notes non finalisées. Éviter passage sur rapport périmé.','US-18;19','À valider métier'),
 ('D09','Indicateurs rattrapage','Incohérence de capture','Capture 17 : 4 non planifiés, 2 inscrits, 1 effectué ; 67 % semble 4/(4+2), pas 4/7. Définir dénominateur. La cible sous 10 % reste un objectif candidat.','US-20;21','À préciser'),
 ('D10','Soutenances et cursus','Dépendances externes','Confirmer conditions exactes : matières des années 1/2/4, stage année 3 et frais réglés. Vérifier services stage/finances ; définir moyenne, validation et impact diplôme.','US-23 à 25;28','Bloquant soutenances'),
 ('D11','Bulletins et mentions','Règles non fournies','Définir coefficients, arrondis, seuils de mention, clôture et publication. Capture 27 affiche des matières sans moyenne marquées complètes : ne pas reproduire cette incohérence.','US-26','Bloquant publication'),
 ('D12','Inscription et pièces','Écrans partiels','Capture indique quatre pièces mais pas leur nature ; configurer liste requise. Confirmation ouvre les services, paiement peut suivre ; préciser services concernés et état du dossier.','US-27;28','À préciser'),
 ('D13','Suppression de compte','Règle à cadrer','Distinguer suspension, désactivation, suppression logique et clôture dossier personnel ; préserver notes et audit. Confirmer email non modifiable et droits précis.','US-29','À préciser'),
 ('D14','Planning et capacité','Hypothèse de travail','Deux semaines et 2 API + 2 frontend sont une proposition. Dates, équipe, vélocité et capacité réelles ne sont pas connues. Les extensions ne sont pas implicitement engagées.','Toutes','À planifier'),
]
sheet('Décisions et écarts',['ID','Sujet','Origine','Décision / point à trancher','Impact','État'],decisions,[12,30,32,130,28,35])

times=['143304','143315','143339','143350','143426','143441','143457','143523','143819','143847','143904','143929','143946','144011','144027','144222','144242','144257','144323','144339','144404','144434','144455','144613','144636','144651','144708','144725','144825']
labels=['Fragment inexploitable','Saisie des notes fermée','Corrections enseignant','Nouvelle demande de correction','Bibliothèque et quota','Dépôt de support','Bibliothèque complète','Profil et affectations','Validation direction — liste','Validation direction — feuille verrouillée','Validation direction — feuille transmise','Corrections direction — décisions','Détail et traçabilité correction','Dossiers personnel — liste','Nouveau dossier — étape compte','Passage de niveau — rapport absent','Plan de rattrapage','Inscription à un rattrapage','Supervision des notes','Contrôle de cohérence','Corrections secrétariat','Soutenances — sessions et candidats','Référentiel des épreuves','Inscription étudiant','Progression verrouillée','Bulletins annuels et versions','Aperçu bulletin','Fiche compte','Ma soutenance sans session']
source_rows=[]
for i,(t,l) in enumerate(zip(times,labels),1):
    related=[s['id'] for s in stories if f'{i:02}' in s['refs']]
    source_rows.append([f'{i:02}',f"Capture d'écran 2026-09-22 {t}.png",l,', '.join(related) or 'Aucune', 'Fragment sans contenu exploitable' if i==1 else 'Référence visuelle fournie ; ne prouve pas la disponibilité API'])
sheet('Sources',['Capture','Fichier fourni','Écran / contenu','US associées','Limite'],source_rows,[12,56,55,75,75])

# Classeur OOXML natif : aucune dépendance tierce requise.
def col(n):
    out=''
    while n: n,r=divmod(n-1,26); out=chr(65+r)+out
    return out
def cell(value,r,c,style):
    ref=f'{col(c)}{r}'
    if isinstance(value,(int,float)):
        return f'<c r="{ref}" s="{style}" t="n"><v>{value}</v></c>'
    return f'<c r="{ref}" s="{style}" t="inlineStr"><is><t xml:space="preserve">{escape(str(value))}</t></is></c>'
styles='''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<fonts count="2"><font><sz val="11"/><color rgb="FF16324F"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>
<fills count="5"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF123D66"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF0F5FA"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE3F3ED"/><bgColor indexed="64"/></patternFill></fill></fills>
<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>
<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
<cellXfs count="5"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFill="1" applyFont="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="3" borderId="0" xfId="0" applyFill="1"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="4" borderId="0" xfId="0" applyFill="1"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="2" fontId="0" fillId="0" borderId="0" xfId="0"><alignment vertical="top"/></xf></cellXfs>
<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>
</styleSheet>'''
out=ROOT/'Sprint_5_EBAC_API_Frontend.xlsx'
with ZipFile(out,'w',ZIP_DEFLATED) as z:
    overrides=''.join(f'<Override PartName="/xl/worksheets/sheet{i}.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' for i in range(1,len(sheets)+1))
    z.writestr('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'+overrides+'</Types>')
    z.writestr('_rels/.rels','<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>')
    z.writestr('xl/workbook.xml','<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView/></bookViews><sheets>'+''.join(f'<sheet name="{escape(s[0])}" sheetId="{i}" r:id="rId{i}"/>' for i,s in enumerate(sheets,1))+'</sheets></workbook>')
    z.writestr('xl/_rels/workbook.xml.rels','<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'+''.join(f'<Relationship Id="rId{i}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet{i}.xml"/>' for i in range(1,len(sheets)+1))+f'<Relationship Id="rId{len(sheets)+1}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>')
    z.writestr('xl/styles.xml',styles)
    for si,(name,headers,rows,widths) in enumerate(sheets,1):
        assert len(headers)==len(widths)
        data=[]
        for ri,row in enumerate([headers]+rows,1):
            assert len(row)==len(headers), (name,ri)
            lines=max(sum(max(1,math.ceil(len(part)/(widths[ci]*0.92))) for part in str(v).split('\n')) for ci,v in enumerate(row))
            h=34 if ri==1 else min(300,max(36,lines*15+12))
            style=1 if ri==1 else (2 if ri%2==0 else 0)
            data.append(f'<row r="{ri}" ht="{h}" customHeight="1">'+''.join(cell(v,ri,ci,3 if v=='P0' else style) for ci,v in enumerate(row,1))+'</row>')
        end=f'{col(len(headers))}{len(rows)+1}'
        xml='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        xml+=f'<dimension ref="A1:{end}"/><sheetViews><sheetView workbookViewId="0" showGridLines="0"><pane xSplit="1" ySplit="1" topLeftCell="B2" activePane="bottomRight" state="frozen"/></sheetView></sheetViews><sheetFormatPr defaultRowHeight="36"/>'
        xml+='<cols>'+''.join(f'<col min="{i}" max="{i}" width="{w}" customWidth="1"/>' for i,w in enumerate(widths,1))+'</cols><sheetData>'+''.join(data)+'</sheetData>'
        xml+=f'<autoFilter ref="A1:{end}"/><pageMargins left="0.3" right="0.3" top="0.5" bottom="0.5" header="0.2" footer="0.2"/><pageSetup orientation="landscape" paperSize="9" fitToWidth="1" fitToHeight="0"/></worksheet>'
        z.writestr(f'xl/worksheets/sheet{si}.xml',xml)

with ZipFile(out) as z:
    assert z.testzip() is None
    for n in z.namelist(): ET.fromstring(z.read(n))
assert len(stories)==30
assert all(s['a']>=0 and s['f']>=0 for s in stories)
print(f'{out}\n{len(sheets)} onglets ; {len(stories)} user stories ; 60 tâches ; 90 critères ; 29 captures. Socle : API {sum_a} j-p / frontend {sum_f} j-p.')
