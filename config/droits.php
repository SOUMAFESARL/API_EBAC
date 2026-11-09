<?php

/*
|--------------------------------------------------------------------------
| Catalogue des droits des espaces de gestion
|--------------------------------------------------------------------------
|
| Un droit est une action sur un sous-module, rangé dans un module :
| son code est `module.sous-module.action` (ex.
| `gestion-academique.etudiants.consulter`). Ce catalogue est le miroir de
| `lib/droits/catalogue.ts` du front (demande backend §69) : le front
| reconnaît un droit par son code exact, en minuscules. Un code ne se
| renomme pas.
|
| Les actions absentes le sont volontairement : on ne supprime ni une note,
| ni un admis, ni une écriture ; on ne rouvre ni une session ni une
| validation. Ne pas les ajouter.
|
| Semé par `Database\Seeders\CatalogueDroitsSeeder`.
|
*/

$crud = ['consulter', 'creer', 'modifier', 'supprimer'];

return [

    'actions' => [
        'consulter' => 'Consulter',
        'creer' => 'Créer',
        'modifier' => 'Modifier',
        'supprimer' => 'Supprimer',
        'archiver' => 'Archiver',
        'activer-annee' => 'Activer une année',
        'publier' => 'Publier',
        'terminer' => 'Terminer',
        'importer' => 'Importer',
        'exporter' => 'Exporter',
        'affecter' => 'Affecter',
        'ouvrir-acces' => "Ouvrir l'accès",
        'rejeter' => 'Rejeter',
        'valider' => 'Valider',
        'executer' => 'Exécuter',
        'transmettre' => 'Transmettre',
        'autoriser' => 'Autoriser',
        'refuser' => 'Refuser',
        'ouvrir-session' => 'Ouvrir une session',
        'programmer' => 'Programmer',
        'cloturer' => 'Clôturer',
        'retirer' => 'Retirer',
        'saisir' => 'Saisir',
        'encaisser' => 'Encaisser',
        'ecriture-corrective' => 'Écriture corrective',
        'relancer' => 'Relancer',
        'bloquer' => 'Bloquer',
        'inscrire' => 'Inscrire',
        'annuler' => 'Annuler',
    ],

    'modules' => [
        'accueil' => [
            'libelle' => 'Accueil',
            'sous_modules' => [
                'tableau-de-bord' => ['Tableau de bord', ['consulter']],
            ],
        ],
        'programme' => [
            'libelle' => 'Programme',
            'sous_modules' => [
                'niveaux' => ['Niveaux', $crud],
                'promotions' => ['Promotions', $crud],
                'domaines' => ["Domaines d'enseignement", $crud],
                'matieres' => ['Matières', $crud],
            ],
        ],
        'planification' => [
            'libelle' => 'Planification des enseignements',
            'sous_modules' => [
                'calendrier-academique' => ['Calendrier académique', ['consulter', 'creer', 'modifier', 'activer-annee']],
                'salles' => ['Salles', ['consulter', 'creer', 'modifier', 'archiver']],
                'repartition-horaire' => ['Répartition horaire', ['consulter', 'modifier']],
                'publication-programme' => ['Publication du programme', ['consulter', 'publier']],
                'affectations' => ['Affectations enseignant', ['consulter', 'creer', 'terminer']],
            ],
        ],
        'gestion-academique' => [
            'libelle' => 'Gestion académique',
            'sous_modules' => [
                'nouvelle-admission' => ['Nouvelle admission', ['consulter', 'importer', 'exporter']],
                'preinscriptions' => ['Pré-inscriptions', ['consulter', 'affecter', 'ouvrir-acces', 'rejeter']],
                'etudiants' => ['Registre étudiants', ['consulter', 'modifier', 'exporter']],
                'stages' => ['Gestion de stage', ['consulter', 'creer', 'modifier']],
                'validation-stages' => ['Validation des stages', ['consulter', 'valider']],
                'eglises' => ['Gestion églises', ['consulter', 'creer', 'modifier']],
            ],
        ],
        'progression' => [
            'libelle' => 'Progression des promotions',
            'sous_modules' => [
                'passage-de-niveau' => ['Passage de niveau', ['consulter', 'executer']],
                'rattrapages' => ['Plan de rattrapage', ['consulter', 'inscrire', 'annuler', 'exporter']],
            ],
        ],
        'notes' => [
            'libelle' => 'Notes & évaluations',
            'sous_modules' => [
                'supervision-notes' => ['Supervision des notes', ['consulter', 'transmettre', 'rejeter']],
                'validation-notes' => ['Validation des notes', ['consulter', 'valider', 'rejeter']],
                'corrections' => ['Correction de notes', ['consulter', 'autoriser', 'refuser']],
                'soutenances' => ['Soutenances', ['consulter', 'ouvrir-session', 'programmer', 'cloturer']],
                'epreuves-soutenance' => ['Liste des épreuves', ['consulter', 'creer', 'modifier', 'retirer']],
                'notes-soutenance' => ['Saisie des notes de soutenance', ['consulter', 'saisir']],
                'validation-soutenances' => ['Validation des soutenances', ['consulter', 'valider']],
                'fin-de-cycle-epreuves' => ['Épreuves de fin de cycle', ['consulter', 'creer', 'modifier', 'retirer']],
                'fin-de-cycle-notes' => ['Notes de fin de cycle', ['consulter', 'saisir']],
            ],
        ],
        'finances' => [
            'libelle' => 'Finances',
            'sous_modules' => [
                'dossiers' => ['Dossiers financiers', ['consulter', 'encaisser', 'ecriture-corrective']],
                'paiement-especes' => ['Paiement en espèces', ['encaisser']],
                'recouvrement' => ['Recouvrement', ['consulter', 'relancer', 'exporter']],
                'parametrage-paiement' => ['Paramétrage paiement', ['consulter', 'modifier']],
            ],
        ],
        'personnel' => [
            'libelle' => 'Gestion du personnel',
            'sous_modules' => [
                'personnel' => ['Dossiers du personnel', ['consulter', 'creer', 'modifier']],
            ],
        ],
        'ressources' => [
            'libelle' => 'Ressources pédagogiques',
            'sous_modules' => [
                'bibliotheque' => ['Bibliothèque pédagogique', ['consulter', 'creer', 'supprimer']],
                'quotas-supports' => ['Quotas de stockage', ['consulter', 'modifier']],
            ],
        ],
        'securite-comptes' => [
            'libelle' => 'Sécurité & comptes',
            'sous_modules' => [
                'utilisateurs' => ['Utilisateurs', ['consulter', 'creer', 'modifier', 'bloquer']],
                'roles-droits' => ['Rôles & droits', $crud],
                'journal-audit' => ["Journal d'audit", ['consulter', 'exporter']],
            ],
        ],
        'parametres' => [
            'libelle' => 'Paramètres',
            'sous_modules' => [
                'parametrage-systeme' => ['Paramétrage système', ['consulter', 'modifier']],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Droits par défaut des rôles semés
    |--------------------------------------------------------------------------
    |
    | Ce que chaque rôle pouvait faire avant la matrice, ni plus ni moins
    | (`docs/EQUIVALENCES_DROITS.md` §D du front). Posés une seule fois, sur
    | un rôle qui n'a encore aucun droit du catalogue : un réglage fait dans
    | l'écran « Rôles & droits » n'est jamais écrasé. ADMIN détient tout sans
    | case à cocher ; les portails (Enseignant, Étudiant, Église) n'ont aucun
    | droit du catalogue.
    |
    */

    'par_defaut' => [
        'direction' => [
            'roles' => ['DIRECTION', 'DIRECTEUR'],
            'droits' => [
                'accueil.tableau-de-bord.consulter',
                'programme.matieres.consulter',
                'planification.affectations.consulter',
                'planification.affectations.creer',
                'planification.affectations.terminer',
                'gestion-academique.nouvelle-admission.consulter',
                'gestion-academique.preinscriptions.ouvrir-acces',
                'gestion-academique.etudiants.consulter',
                'gestion-academique.validation-stages.consulter',
                'gestion-academique.validation-stages.valider',
                'notes.validation-notes.consulter',
                'notes.validation-notes.valider',
                'notes.validation-notes.rejeter',
                'notes.corrections.consulter',
                'notes.corrections.autoriser',
                'notes.corrections.refuser',
                'notes.validation-soutenances.consulter',
                'notes.validation-soutenances.valider',
                'notes.fin-de-cycle-epreuves.consulter',
                'notes.fin-de-cycle-notes.consulter',
                'finances.recouvrement.consulter',
                'personnel.personnel.consulter',
                'personnel.personnel.creer',
                'personnel.personnel.modifier',
                'securite-comptes.utilisateurs.consulter',
                'securite-comptes.utilisateurs.creer',
                'securite-comptes.journal-audit.consulter',
                'securite-comptes.journal-audit.exporter',
            ],
        ],
        'secretariat-academique' => [
            'roles' => ['SECRETARIAT', 'SECRETAIRE_ACADEMIQUE', 'SECRETARIAT_ACADEMIQUE'],
            'droits' => [
                'accueil.tableau-de-bord.consulter',
                'gestion-academique.nouvelle-admission.consulter',
                'gestion-academique.nouvelle-admission.importer',
                'gestion-academique.nouvelle-admission.exporter',
                'gestion-academique.preinscriptions.consulter',
                'gestion-academique.preinscriptions.affecter',
                'gestion-academique.preinscriptions.ouvrir-acces',
                'gestion-academique.etudiants.consulter',
                'gestion-academique.etudiants.modifier',
                'gestion-academique.etudiants.exporter',
                'gestion-academique.stages.consulter',
                'gestion-academique.stages.creer',
                'gestion-academique.stages.modifier',
                'gestion-academique.eglises.consulter',
                'gestion-academique.eglises.creer',
                'gestion-academique.eglises.modifier',
                'progression.passage-de-niveau.consulter',
                'progression.passage-de-niveau.executer',
                'progression.rattrapages.consulter',
                'progression.rattrapages.inscrire',
                'progression.rattrapages.annuler',
                'progression.rattrapages.exporter',
                'notes.supervision-notes.consulter',
                'notes.supervision-notes.transmettre',
                'notes.supervision-notes.rejeter',
                'notes.corrections.consulter',
                'notes.corrections.autoriser',
                'notes.corrections.refuser',
                'notes.soutenances.consulter',
                'notes.soutenances.ouvrir-session',
                'notes.soutenances.programmer',
                'notes.soutenances.cloturer',
                'notes.epreuves-soutenance.consulter',
                'notes.epreuves-soutenance.creer',
                'notes.epreuves-soutenance.modifier',
                'notes.epreuves-soutenance.retirer',
                'notes.notes-soutenance.consulter',
                'notes.notes-soutenance.saisir',
                'notes.fin-de-cycle-epreuves.consulter',
                'notes.fin-de-cycle-epreuves.creer',
                'notes.fin-de-cycle-epreuves.modifier',
                'notes.fin-de-cycle-epreuves.retirer',
                'notes.fin-de-cycle-notes.consulter',
                'notes.fin-de-cycle-notes.saisir',
            ],
        ],
        'gestionnaire' => [
            'roles' => ['GESTIONNAIRE'],
            'droits' => [
                'accueil.tableau-de-bord.consulter',
                'gestion-academique.etudiants.consulter',
                'finances.dossiers.consulter',
                'finances.dossiers.encaisser',
                'finances.dossiers.ecriture-corrective',
                'finances.paiement-especes.encaisser',
                'finances.recouvrement.consulter',
                'finances.recouvrement.relancer',
                'finances.recouvrement.exporter',
                'finances.parametrage-paiement.consulter',
                'finances.parametrage-paiement.modifier',
            ],
        ],
    ],

];
