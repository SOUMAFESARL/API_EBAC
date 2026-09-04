-- Schéma relationnel EBAC SIG
-- Cible : MySQL 8.0+ / MariaDB 10.6+
-- Source fonctionnelle : EBAC_SIG.html

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    libelle VARCHAR(100) NOT NULL,
    description TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    deleted_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(100) NOT NULL UNIQUE,
    libelle VARCHAR(150) NOT NULL,
    categorie VARCHAR(100) NULL,
    description TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    deleted_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS menus (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_parent BIGINT UNSIGNED NULL,
    code VARCHAR(100) NOT NULL UNIQUE,
    libelle VARCHAR(150) NOT NULL,
    description VARCHAR(255) NULL,
    route VARCHAR(180) NULL,
    route_active VARCHAR(180) NULL,
    icone VARCHAR(100) NULL,
    groupe VARCHAR(100) NULL,
    ordre SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    visible BOOLEAN NOT NULL DEFAULT TRUE,
    actif BOOLEAN NOT NULL DEFAULT TRUE,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    INDEX idx_menus_parent_ordre (id_parent, ordre),
    INDEX idx_menus_groupe_ordre (groupe, ordre),
    CONSTRAINT fk_menus_parent FOREIGN KEY (id_parent) REFERENCES menus(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS menu_permissions (
    id_menu BIGINT UNSIGNED NOT NULL,
    id_permission BIGINT UNSIGNED NOT NULL,
    permission_principale BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NULL,
    PRIMARY KEY (id_menu, id_permission),
    INDEX idx_menu_permissions_permission (id_permission),
    CONSTRAINT fk_menu_permissions_menu FOREIGN KEY (id_menu) REFERENCES menus(id) ON DELETE CASCADE,
    CONSTRAINT fk_menu_permissions_permission FOREIGN KEY (id_permission) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    id_role BIGINT UNSIGNED NOT NULL,
    id_permission BIGINT UNSIGNED NOT NULL,
    actif BOOLEAN NOT NULL DEFAULT TRUE,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    deleted_at TIMESTAMP NULL,
    PRIMARY KEY (id_role, id_permission),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (id_role) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission FOREIGN KEY (id_permission) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    matricule VARCHAR(50) NULL UNIQUE,
    code VARCHAR(150) NOT NULL,
    user_code VARCHAR(150) NOT NULL,
    user_id VARCHAR(150) NOT NULL,
    nom VARCHAR(150) NOT NULL,
    prenoms VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    telephone VARCHAR(30) NULL,
    fonction VARCHAR(100) NULL,
    departement VARCHAR(150) NULL,
    date_embauche DATE NULL,
    password VARCHAR(255) NOT NULL,
    photo VARCHAR(255) NULL,
    civilite_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    statut VARCHAR(30) NOT NULL DEFAULT 'Actif',
    tentatives_echouees TINYINT UNSIGNED NOT NULL DEFAULT 0,
    deux_fa_active BOOLEAN NOT NULL DEFAULT FALSE,
    cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    derniere_connexion DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    created_by VARCHAR(150) NULL,
    created_by VARCHAR(150) NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    deleted_at TIMESTAMP NULL,
    INDEX idx_users_role (id_role),
    INDEX idx_users_statut (statut),
    CONSTRAINT fk_users_role FOREIGN KEY (id_role) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS civilite (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(10) NOT NULL,
    name VARCHAR(50) NOT NULL,
    abreviation VARCHAR(10) NULL,
    description VARCHAR(255) NULL,
    actif BOOLEAN NOT NULL DEFAULT TRUE,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uk_civilite_code (code),
    INDEX idx_civilite_actif (actif),

    CONSTRAINT fk_civilite_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_civilite_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_civilite_deleted_by
        FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS configurations_smtp (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    host VARCHAR(255) NOT NULL,
    port SMALLINT UNSIGNED NOT NULL DEFAULT 465,
    username TEXT NOT NULL,
    password TEXT NOT NULL COMMENT 'Valeur chiffrée par l’application',
    scheme ENUM('smtp', 'smtps') NOT NULL DEFAULT 'smtps',
    from_address VARCHAR(255) NOT NULL,
    from_name VARCHAR(255) NULL,
    actif BOOLEAN NOT NULL DEFAULT TRUE,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uk_configurations_smtp_serveur (host, port, from_address),
    INDEX idx_configurations_smtp_actif (actif),

    CONSTRAINT fk_configurations_smtp_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_configurations_smtp_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_configurations_smtp_deleted_by
        FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS eglises (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    nom VARCHAR(180) NOT NULL,
    denomination VARCHAR(180) NULL,
    adresse VARCHAR(255) NULL,
    region VARCHAR(120) NULL,
    district VARCHAR(120) NULL,
    ville_commune VARCHAR(120) NOT NULL,
    telephone VARCHAR(30) NULL,
    email VARCHAR(150) NULL,
    statut ENUM('Active', 'Suspendue', 'Archivée') NOT NULL DEFAULT 'Active',
    capacite_max_stagiaires SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    representants JSON NULL,
    observations TEXT NULL,
    user_id BIGINT UNSIGNED NULL,
    user_code VARCHAR(150) NULL COMMENT 'Copie de users.code du compte Église associé',
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    INDEX idx_eglises_statut_ville (statut, ville_commune),
    INDEX idx_eglises_nom (nom),
    INDEX idx_eglises_user_code (user_code),
    CONSTRAINT fk_eglises_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_eglises_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_eglises_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_eglises_deleted_by FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS annees_academiques (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    libelle VARCHAR(20) NOT NULL UNIQUE,
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    active BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT chk_annee_dates CHECK (date_fin > date_debut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS semestres (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_annee_academique BIGINT UNSIGNED NOT NULL,
    code VARCHAR(20) NOT NULL,
    libelle VARCHAR(100) NOT NULL,
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    statut VARCHAR(30) NOT NULL DEFAULT 'Planifié',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uk_semestre_annee_code (id_annee_academique, code),
    CONSTRAINT fk_semestres_annee FOREIGN KEY (id_annee_academique) REFERENCES annees_academiques(id) ON DELETE CASCADE,
    CONSTRAINT chk_semestre_dates CHECK (date_fin > date_debut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS niveaux (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    libelle VARCHAR(100) NOT NULL,
    ordre TINYINT UNSIGNED NOT NULL,
    description TEXT NULL,
    actif BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS promotions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    id_annee_academique BIGINT UNSIGNED NOT NULL,
    id_niveau BIGINT UNSIGNED NOT NULL,
    capacite SMALLINT UNSIGNED NULL,
    statut VARCHAR(30) NOT NULL DEFAULT 'Active',
    date_ouverture DATE NULL,
    date_cloture DATE NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    CONSTRAINT fk_promotions_annee FOREIGN KEY (id_annee_academique) REFERENCES annees_academiques(id),
    CONSTRAINT fk_promotions_niveau FOREIGN KEY (id_niveau) REFERENCES niveaux(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS etudiants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL UNIQUE,
    matricule VARCHAR(50) NULL UNIQUE,
    nom VARCHAR(150) NOT NULL,
    prenoms VARCHAR(150) NOT NULL,
    sexe VARCHAR(20) NULL,
    date_naissance DATE NULL,
    lieu_naissance VARCHAR(150) NULL,
    nationalite VARCHAR(80) NULL,
    email VARCHAR(150) NULL,
    telephone VARCHAR(30) NULL,
    adresse VARCHAR(255) NULL,
    eglise_id BIGINT UNSIGNED NULL,
    date_inscription DATE NOT NULL,
    statut VARCHAR(50) NOT NULL DEFAULT 'En formation',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    INDEX idx_etudiants_nom (nom, prenoms),
    INDEX idx_etudiants_statut (statut),
    CONSTRAINT fk_etudiants_user FOREIGN KEY (id_user) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_etudiants_eglise FOREIGN KEY (id_eglise) REFERENCES eglises(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_etudiant BIGINT UNSIGNED NOT NULL,
    id_promotion BIGINT UNSIGNED NOT NULL,
    date_inscription DATE NOT NULL,
    statut VARCHAR(40) NOT NULL DEFAULT 'En formation',
    decision_passage VARCHAR(50) NULL,
    date_decision DATETIME NULL,
    observations TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uk_inscription_etudiant_promotion (id_etudiant, id_promotion),
    CONSTRAINT fk_inscriptions_etudiant FOREIGN KEY (id_etudiant) REFERENCES etudiants(id),
    CONSTRAINT fk_inscriptions_promotion FOREIGN KEY (id_promotion) REFERENCES promotions(id),
    CONSTRAINT fk_inscriptions_createur FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dossiers_etudiants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_etudiant BIGINT UNSIGNED NOT NULL UNIQUE,
    numero_dossier VARCHAR(50) NOT NULL UNIQUE,
    statut VARCHAR(30) NOT NULL DEFAULT 'Incomplet',
    date_ouverture DATE NOT NULL,
    pieces_requises JSON NULL,
    observations TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT fk_dossiers_etudiant FOREIGN KEY (id_etudiant) REFERENCES etudiants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS unites_valeur (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    libelle VARCHAR(180) NOT NULL,
    id_niveau BIGINT UNSIGNED NOT NULL,
    coefficient DECIMAL(5,2) NOT NULL DEFAULT 1,
    type VARCHAR(50) NULL,
    description TEXT NULL,
    objectifs TEXT NULL,
    prerequis TEXT NULL,
    note_validation DECIMAL(5,2) NOT NULL DEFAULT 10,
    obligatoire BOOLEAN NOT NULL DEFAULT TRUE,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    CONSTRAINT fk_uv_niveau FOREIGN KEY (id_niveau) REFERENCES niveaux(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS modules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_unite_valeur BIGINT UNSIGNED NOT NULL,
    code VARCHAR(50) NULL,
    libelle VARCHAR(180) NOT NULL,
    ordre SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    description TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uk_module_uv_libelle (id_unite_valeur, libelle),
    CONSTRAINT fk_modules_uv FOREIGN KEY (id_unite_valeur) REFERENCES unites_valeur(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cours (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_module BIGINT UNSIGNED NOT NULL,
    code VARCHAR(50) NULL,
    libelle VARCHAR(180) NOT NULL,
    volume_horaire DECIMAL(6,2) NOT NULL DEFAULT 0,
    coefficient DECIMAL(5,2) NOT NULL DEFAULT 1,
    ordre SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    actif BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uk_cours_module_libelle (id_module, libelle),
    CONSTRAINT fk_cours_module FOREIGN KEY (id_module) REFERENCES modules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS affectations_enseignants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_enseignant BIGINT UNSIGNED NOT NULL,
    id_unite_valeur BIGINT UNSIGNED NOT NULL,
    id_promotion BIGINT UNSIGNED NOT NULL,
    id_semestre BIGINT UNSIGNED NOT NULL,
    date_affectation DATE NOT NULL,
    statut VARCHAR(30) NOT NULL DEFAULT 'Active',
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uk_affectation (id_enseignant, id_unite_valeur, id_promotion, id_semestre),
    CONSTRAINT fk_affectations_enseignant FOREIGN KEY (id_enseignant) REFERENCES users(id),
    CONSTRAINT fk_affectations_uv FOREIGN KEY (id_unite_valeur) REFERENCES unites_valeur(id),
    CONSTRAINT fk_affectations_promotion FOREIGN KEY (id_promotion) REFERENCES promotions(id),
    CONSTRAINT fk_affectations_semestre FOREIGN KEY (id_semestre) REFERENCES semestres(id),
    CONSTRAINT fk_affectations_createur FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS salles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    libelle VARCHAR(100) NOT NULL,
    capacite SMALLINT UNSIGNED NULL,
    localisation VARCHAR(150) NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS emploi_du_temps (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_cours BIGINT UNSIGNED NOT NULL,
    id_promotion BIGINT UNSIGNED NOT NULL,
    id_enseignant BIGINT UNSIGNED NOT NULL,
    id_salle BIGINT UNSIGNED NULL,
    id_semestre BIGINT UNSIGNED NOT NULL,
    jour_semaine TINYINT UNSIGNED NOT NULL,
    heure_debut TIME NOT NULL,
    heure_fin TIME NOT NULL,
    date_debut DATE NULL,
    date_fin DATE NULL,
    statut VARCHAR(30) NOT NULL DEFAULT 'Planifié',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_edt_creneau (jour_semaine, heure_debut, heure_fin),
    CONSTRAINT fk_edt_cours FOREIGN KEY (id_cours) REFERENCES cours(id),
    CONSTRAINT fk_edt_promotion FOREIGN KEY (id_promotion) REFERENCES promotions(id),
    CONSTRAINT fk_edt_enseignant FOREIGN KEY (id_enseignant) REFERENCES users(id),
    CONSTRAINT fk_edt_salle FOREIGN KEY (id_salle) REFERENCES salles(id) ON DELETE SET NULL,
    CONSTRAINT fk_edt_semestre FOREIGN KEY (id_semestre) REFERENCES semestres(id),
    CONSTRAINT chk_edt_heures CHECK (heure_fin > heure_debut),
    CONSTRAINT chk_edt_jour CHECK (jour_semaine BETWEEN 1 AND 7)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS publications_emploi_du_temps (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_semestre BIGINT UNSIGNED NOT NULL,
    version SMALLINT UNSIGNED NOT NULL,
    publie BOOLEAN NOT NULL DEFAULT FALSE,
    publie_par BIGINT UNSIGNED NULL,
    publie_le DATETIME NULL,
    observations TEXT NULL,
    UNIQUE KEY uk_publication_edt_version (id_semestre, version),
    CONSTRAINT fk_publications_edt_semestre FOREIGN KEY (id_semestre) REFERENCES semestres(id),
    CONSTRAINT fk_publications_edt_user FOREIGN KEY (publie_par) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seances (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_cours BIGINT UNSIGNED NOT NULL,
    id_promotion BIGINT UNSIGNED NOT NULL,
    id_enseignant BIGINT UNSIGNED NOT NULL,
    id_emploi_du_temps BIGINT UNSIGNED NULL,
    date_prevue DATE NOT NULL,
    heure_prevue TIME NULL,
    date_effective DATE NULL,
    heure_effective TIME NULL,
    duree_minutes SMALLINT UNSIGNED NULL,
    theme TEXT NULL,
    statut VARCHAR(30) NOT NULL DEFAULT 'Prévue',
    motif TEXT NULL,
    observations TEXT NULL,
    supports_utilises TEXT NULL,
    verrouillee BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT fk_seances_cours FOREIGN KEY (id_cours) REFERENCES cours(id),
    CONSTRAINT fk_seances_promotion FOREIGN KEY (id_promotion) REFERENCES promotions(id),
    CONSTRAINT fk_seances_enseignant FOREIGN KEY (id_enseignant) REFERENCES users(id),
    CONSTRAINT fk_seances_edt FOREIGN KEY (id_emploi_du_temps) REFERENCES emploi_du_temps(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS presences (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_seance BIGINT UNSIGNED NOT NULL,
    id_etudiant BIGINT UNSIGNED NOT NULL,
    statut VARCHAR(20) NOT NULL DEFAULT 'Présent',
    motif_absence TEXT NULL,
    validee_par BIGINT UNSIGNED NULL,
    validee_le DATETIME NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uk_presence_seance_etudiant (id_seance, id_etudiant),
    CONSTRAINT fk_presences_seance FOREIGN KEY (id_seance) REFERENCES seances(id) ON DELETE CASCADE,
    CONSTRAINT fk_presences_etudiant FOREIGN KEY (id_etudiant) REFERENCES etudiants(id) ON DELETE CASCADE,
    CONSTRAINT fk_presences_validateur FOREIGN KEY (validee_par) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evaluations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_cours BIGINT UNSIGNED NOT NULL,
    id_promotion BIGINT UNSIGNED NOT NULL,
    id_semestre BIGINT UNSIGNED NOT NULL,
    libelle VARCHAR(150) NOT NULL,
    type VARCHAR(50) NOT NULL,
    date_evaluation DATE NULL,
    coefficient DECIMAL(5,2) NOT NULL DEFAULT 1,
    bareme DECIMAL(5,2) NOT NULL DEFAULT 20,
    statut VARCHAR(30) NOT NULL DEFAULT 'Brouillon',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT fk_evaluations_cours FOREIGN KEY (id_cours) REFERENCES cours(id),
    CONSTRAINT fk_evaluations_promotion FOREIGN KEY (id_promotion) REFERENCES promotions(id),
    CONSTRAINT fk_evaluations_semestre FOREIGN KEY (id_semestre) REFERENCES semestres(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_evaluation BIGINT UNSIGNED NOT NULL,
    id_etudiant BIGINT UNSIGNED NOT NULL,
    note DECIMAL(5,2) NULL,
    absent BOOLEAN NOT NULL DEFAULT FALSE,
    observation TEXT NULL,
    saisie_par BIGINT UNSIGNED NULL,
    saisie_le DATETIME NULL,
    statut VARCHAR(40) NOT NULL DEFAULT 'Brouillon',
    verrouillee BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uk_note_evaluation_etudiant (id_evaluation, id_etudiant),
    CONSTRAINT fk_notes_evaluation FOREIGN KEY (id_evaluation) REFERENCES evaluations(id) ON DELETE CASCADE,
    CONSTRAINT fk_notes_etudiant FOREIGN KEY (id_etudiant) REFERENCES etudiants(id) ON DELETE CASCADE,
    CONSTRAINT fk_notes_saisie_user FOREIGN KEY (saisie_par) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT chk_notes_note CHECK (note IS NULL OR note >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS validations_notes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_evaluation BIGINT UNSIGNED NOT NULL,
    etape VARCHAR(50) NOT NULL,
    statut VARCHAR(50) NOT NULL,
    id_acteur BIGINT UNSIGNED NULL,
    commentaire TEXT NULL,
    traite_le DATETIME NULL,
    created_at TIMESTAMP NULL,
    UNIQUE KEY uk_validation_note_etape (id_evaluation, etape),
    CONSTRAINT fk_validations_evaluation FOREIGN KEY (id_evaluation) REFERENCES evaluations(id) ON DELETE CASCADE,
    CONSTRAINT fk_validations_acteur FOREIGN KEY (id_acteur) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS corrections_notes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_note BIGINT UNSIGNED NOT NULL,
    note_initiale DECIMAL(5,2) NOT NULL,
    note_proposee DECIMAL(5,2) NOT NULL,
    note_finale DECIMAL(5,2) NULL,
    motif TEXT NOT NULL,
    demande_par BIGINT UNSIGNED NOT NULL,
    autorisee_par BIGINT UNSIGNED NULL,
    appliquee_par BIGINT UNSIGNED NULL,
    statut VARCHAR(50) NOT NULL DEFAULT 'En attente autorisation',
    demandee_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    autorisee_le DATETIME NULL,
    appliquee_le DATETIME NULL,
    CONSTRAINT fk_corrections_note FOREIGN KEY (id_note) REFERENCES notes(id),
    CONSTRAINT fk_corrections_demandeur FOREIGN KEY (demande_par) REFERENCES users(id),
    CONSTRAINT fk_corrections_autorisateur FOREIGN KEY (autorisee_par) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_corrections_applicateur FOREIGN KEY (appliquee_par) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS traces_corrections_notes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_correction BIGINT UNSIGNED NOT NULL,
    id_acteur BIGINT UNSIGNED NULL,
    action VARCHAR(150) NOT NULL,
    details TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_traces_correction FOREIGN KEY (id_correction) REFERENCES corrections_notes(id) ON DELETE CASCADE,
    CONSTRAINT fk_traces_correction_acteur FOREIGN KEY (id_acteur) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bulletins (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_etudiant BIGINT UNSIGNED NOT NULL,
    id_semestre BIGINT UNSIGNED NOT NULL,
    id_promotion BIGINT UNSIGNED NOT NULL,
    version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    moyenne_generale DECIMAL(5,2) NULL,
    mention VARCHAR(100) NULL,
    decision VARCHAR(100) NULL,
    statut VARCHAR(30) NOT NULL DEFAULT 'Brouillon',
    fichier VARCHAR(255) NULL,
    genere_par BIGINT UNSIGNED NULL,
    publie_par BIGINT UNSIGNED NULL,
    genere_le DATETIME NULL,
    publie_le DATETIME NULL,
    archive BOOLEAN NOT NULL DEFAULT FALSE,
    UNIQUE KEY uk_bulletin_version (id_etudiant, id_semestre, version),
    CONSTRAINT fk_bulletins_etudiant FOREIGN KEY (id_etudiant) REFERENCES etudiants(id),
    CONSTRAINT fk_bulletins_semestre FOREIGN KEY (id_semestre) REFERENCES semestres(id),
    CONSTRAINT fk_bulletins_promotion FOREIGN KEY (id_promotion) REFERENCES promotions(id),
    CONSTRAINT fk_bulletins_generateur FOREIGN KEY (genere_par) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_bulletins_publicateur FOREIGN KEY (publie_par) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lignes_bulletins (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_bulletin BIGINT UNSIGNED NOT NULL,
    id_unite_valeur BIGINT UNSIGNED NOT NULL,
    moyenne DECIMAL(5,2) NULL,
    coefficient DECIMAL(5,2) NOT NULL,
    points DECIMAL(8,2) NULL,
    statut VARCHAR(30) NULL,
    UNIQUE KEY uk_ligne_bulletin_uv (id_bulletin, id_unite_valeur),
    CONSTRAINT fk_lignes_bulletin FOREIGN KEY (id_bulletin) REFERENCES bulletins(id) ON DELETE CASCADE,
    CONSTRAINT fk_lignes_bulletin_uv FOREIGN KEY (id_unite_valeur) REFERENCES unites_valeur(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sessions_rattrapage (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_cours BIGINT UNSIGNED NOT NULL,
    id_promotion BIGINT UNSIGNED NOT NULL,
    id_enseignant BIGINT UNSIGNED NOT NULL,
    id_salle BIGINT UNSIGNED NULL,
    date_session DATE NOT NULL,
    heure_debut TIME NOT NULL,
    heure_fin TIME NOT NULL,
    capacite SMALLINT UNSIGNED NULL,
    statut VARCHAR(30) NOT NULL DEFAULT 'Planifiée',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT fk_rattrapage_cours FOREIGN KEY (id_cours) REFERENCES cours(id),
    CONSTRAINT fk_rattrapage_promotion FOREIGN KEY (id_promotion) REFERENCES promotions(id),
    CONSTRAINT fk_rattrapage_enseignant FOREIGN KEY (id_enseignant) REFERENCES users(id),
    CONSTRAINT fk_rattrapage_salle FOREIGN KEY (id_salle) REFERENCES salles(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inscriptions_rattrapage (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_session_rattrapage BIGINT UNSIGNED NOT NULL,
    id_etudiant BIGINT UNSIGNED NOT NULL,
    id_note_origine BIGINT UNSIGNED NULL,
    note_rattrapage DECIMAL(5,2) NULL,
    statut VARCHAR(40) NOT NULL DEFAULT 'Inscrit',
    inscrit_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_inscription_rattrapage (id_session_rattrapage, id_etudiant),
    CONSTRAINT fk_inscriptions_rattrapage_session FOREIGN KEY (id_session_rattrapage) REFERENCES sessions_rattrapage(id) ON DELETE CASCADE,
    CONSTRAINT fk_inscriptions_rattrapage_etudiant FOREIGN KEY (id_etudiant) REFERENCES etudiants(id) ON DELETE CASCADE,
    CONSTRAINT fk_inscriptions_rattrapage_note FOREIGN KEY (id_note_origine) REFERENCES notes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tarifs_scolarite (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_annee_academique BIGINT UNSIGNED NOT NULL,
    id_niveau BIGINT UNSIGNED NOT NULL,
    libelle VARCHAR(150) NOT NULL,
    montant_total DECIMAL(12,2) NOT NULL,
    devise CHAR(3) NOT NULL DEFAULT 'XOF',
    actif BOOLEAN NOT NULL DEFAULT TRUE,
    UNIQUE KEY uk_tarif_annee_niveau (id_annee_academique, id_niveau),
    CONSTRAINT fk_tarifs_annee FOREIGN KEY (id_annee_academique) REFERENCES annees_academiques(id),
    CONSTRAINT fk_tarifs_niveau FOREIGN KEY (id_niveau) REFERENCES niveaux(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS echeances (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_inscription BIGINT UNSIGNED NOT NULL,
    libelle VARCHAR(180) NOT NULL,
    montant DECIMAL(12,2) NOT NULL,
    date_echeance DATE NOT NULL,
    ordre SMALLINT UNSIGNED NOT NULL,
    statut VARCHAR(30) NOT NULL DEFAULT 'À payer',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uk_echeance_inscription_ordre (id_inscription, ordre),
    CONSTRAINT fk_echeances_inscription FOREIGN KEY (id_inscription) REFERENCES inscriptions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS paiements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_inscription BIGINT UNSIGNED NOT NULL,
    id_echeance BIGINT UNSIGNED NULL,
    reference VARCHAR(50) NOT NULL UNIQUE,
    montant DECIMAL(12,2) NOT NULL,
    devise CHAR(3) NOT NULL DEFAULT 'XOF',
    mode_paiement VARCHAR(50) NOT NULL,
    reference_transaction VARCHAR(100) NULL,
    date_paiement DATETIME NOT NULL,
    statut VARCHAR(30) NOT NULL DEFAULT 'En attente',
    valide_par BIGINT UNSIGNED NULL,
    valide_le DATETIME NULL,
    justificatif VARCHAR(255) NULL,
    observations TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_paiements_date (date_paiement),
    CONSTRAINT fk_paiements_inscription FOREIGN KEY (id_inscription) REFERENCES inscriptions(id),
    CONSTRAINT fk_paiements_echeance FOREIGN KEY (id_echeance) REFERENCES echeances(id) ON DELETE SET NULL,
    CONSTRAINT fk_paiements_validateur FOREIGN KEY (valide_par) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_etudiant BIGINT UNSIGNED NOT NULL,
    id_eglise BIGINT UNSIGNED NULL,
    id_annee_academique BIGINT UNSIGNED NOT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'Stage L3',
    organisme_accueil VARCHAR(180) NULL,
    maitre_stage VARCHAR(180) NULL,
    date_debut DATE NULL,
    date_fin DATE NULL,
    sujet TEXT NULL,
    statut VARCHAR(40) NOT NULL DEFAULT 'Planifié',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT fk_stages_etudiant FOREIGN KEY (id_etudiant) REFERENCES etudiants(id),
    CONSTRAINT fk_stages_eglise FOREIGN KEY (id_eglise) REFERENCES eglises(id) ON DELETE SET NULL,
    CONSTRAINT fk_stages_annee FOREIGN KEY (id_annee_academique) REFERENCES annees_academiques(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rapports_stage (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_stage BIGINT UNSIGNED NOT NULL,
    titre VARCHAR(255) NOT NULL,
    type_rapport VARCHAR(50) NOT NULL,
    fichier VARCHAR(255) NULL,
    taille_octets BIGINT UNSIGNED NULL,
    version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    statut VARCHAR(30) NOT NULL DEFAULT 'Attendu',
    depose_le DATETIME NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uk_rapport_stage_version (id_stage, type_rapport, version),
    CONSTRAINT fk_rapports_stage FOREIGN KEY (id_stage) REFERENCES stages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evaluations_stage (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_stage BIGINT UNSIGNED NOT NULL,
    id_evaluateur BIGINT UNSIGNED NULL,
    ponctualite TINYINT UNSIGNED NULL,
    integration TINYINT UNSIGNED NULL,
    initiative TINYINT UNSIGNED NULL,
    competences TINYINT UNSIGNED NULL,
    appreciation TEXT NULL,
    note DECIMAL(5,2) NULL,
    statut VARCHAR(30) NOT NULL DEFAULT 'Brouillon',
    evalue_le DATETIME NULL,
    CONSTRAINT fk_evaluations_stage FOREIGN KEY (id_stage) REFERENCES stages(id) ON DELETE CASCADE,
    CONSTRAINT fk_evaluations_stage_user FOREIGN KEY (id_evaluateur) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS soutenances (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_etudiant BIGINT UNSIGNED NOT NULL,
    id_annee_academique BIGINT UNSIGNED NOT NULL,
    titre_memoire VARCHAR(255) NOT NULL,
    date_soutenance DATETIME NULL,
    lieu VARCHAR(150) NULL,
    jury JSON NULL,
    note DECIMAL(5,2) NULL,
    mention VARCHAR(100) NULL,
    statut VARCHAR(30) NOT NULL DEFAULT 'Planifiée',
    valide_par BIGINT UNSIGNED NULL,
    valide_le DATETIME NULL,
    CONSTRAINT fk_soutenances_etudiant FOREIGN KEY (id_etudiant) REFERENCES etudiants(id),
    CONSTRAINT fk_soutenances_annee FOREIGN KEY (id_annee_academique) REFERENCES annees_academiques(id),
    CONSTRAINT fk_soutenances_validateur FOREIGN KEY (valide_par) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS diplomes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_etudiant BIGINT UNSIGNED NOT NULL,
    id_promotion BIGINT UNSIGNED NOT NULL,
    numero VARCHAR(80) NOT NULL UNIQUE,
    intitule VARCHAR(180) NOT NULL,
    mention VARCHAR(100) NULL,
    date_obtention DATE NOT NULL,
    date_delivrance DATE NULL,
    statut VARCHAR(30) NOT NULL DEFAULT 'À délivrer',
    fichier VARCHAR(255) NULL,
    delivre_par BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT fk_diplomes_etudiant FOREIGN KEY (id_etudiant) REFERENCES etudiants(id),
    CONSTRAINT fk_diplomes_promotion FOREIGN KEY (id_promotion) REFERENCES promotions(id),
    CONSTRAINT fk_diplomes_user FOREIGN KEY (delivre_par) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supports_pedagogiques (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_cours BIGINT UNSIGNED NOT NULL,
    id_auteur BIGINT UNSIGNED NULL,
    nom VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL,
    visible_etudiants BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    CONSTRAINT fk_supports_cours FOREIGN KEY (id_cours) REFERENCES cours(id) ON DELETE CASCADE,
    CONSTRAINT fk_supports_auteur FOREIGN KEY (id_auteur) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS versions_supports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_support BIGINT UNSIGNED NOT NULL,
    version SMALLINT UNSIGNED NOT NULL,
    fichier VARCHAR(255) NOT NULL,
    format VARCHAR(20) NULL,
    taille_octets BIGINT UNSIGNED NULL,
    note_version TEXT NULL,
    depose_par BIGINT UNSIGNED NULL,
    depose_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_version_support (id_support, version),
    CONSTRAINT fk_versions_support FOREIGN KEY (id_support) REFERENCES supports_pedagogiques(id) ON DELETE CASCADE,
    CONSTRAINT fk_versions_support_user FOREIGN KEY (depose_par) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recommandations_eglises (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_eglise BIGINT UNSIGNED NOT NULL,
    id_etudiant BIGINT UNSIGNED NOT NULL,
    recommande_par VARCHAR(180) NULL,
    date_recommandation DATE NULL,
    statut VARCHAR(30) NOT NULL DEFAULT 'Active',
    observations TEXT NULL,
    UNIQUE KEY uk_recommandation_eglise_etudiant (id_eglise, id_etudiant),
    CONSTRAINT fk_recommandations_eglise FOREIGN KEY (id_eglise) REFERENCES eglises(id),
    CONSTRAINT fk_recommandations_etudiant FOREIGN KEY (id_etudiant) REFERENCES etudiants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS suivis_eglises (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_recommandation BIGINT UNSIGNED NOT NULL,
    id_auteur BIGINT UNSIGNED NULL,
    type VARCHAR(50) NOT NULL,
    titre VARCHAR(180) NOT NULL,
    contenu TEXT NULL,
    date_suivi DATE NOT NULL,
    fichier VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    CONSTRAINT fk_suivis_recommandation FOREIGN KEY (id_recommandation) REFERENCES recommandations_eglises(id) ON DELETE CASCADE,
    CONSTRAINT fk_suivis_auteur FOREIGN KEY (id_auteur) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS documents_archives (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_etudiant BIGINT UNSIGNED NULL,
    id_bulletin BIGINT UNSIGNED NULL,
    type VARCHAR(100) NOT NULL,
    titre VARCHAR(255) NOT NULL,
    reference VARCHAR(80) NULL UNIQUE,
    fichier VARCHAR(255) NOT NULL,
    version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    motif_archivage VARCHAR(255) NULL,
    archive_par BIGINT UNSIGNED NULL,
    archive_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_archives_type (type),
    CONSTRAINT fk_archives_etudiant FOREIGN KEY (id_etudiant) REFERENCES etudiants(id) ON DELETE SET NULL,
    CONSTRAINT fk_archives_bulletin FOREIGN KEY (id_bulletin) REFERENCES bulletins(id) ON DELETE SET NULL,
    CONSTRAINT fk_archives_user FOREIGN KEY (archive_par) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS calendrier_evenements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_annee_academique BIGINT UNSIGNED NOT NULL,
    type VARCHAR(50) NOT NULL,
    titre VARCHAR(180) NOT NULL,
    date_debut DATETIME NOT NULL,
    date_fin DATETIME NULL,
    description TEXT NULL,
    actif BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT fk_calendrier_annee FOREIGN KEY (id_annee_academique) REFERENCES annees_academiques(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    id CHAR(36) PRIMARY KEY,
    type VARCHAR(255) NOT NULL,
    notifiable_type VARCHAR(255) NOT NULL,
    notifiable_id BIGINT UNSIGNED NOT NULL,
    data JSON NOT NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_notifications_notifiable (notifiable_type, notifiable_id),
    INDEX idx_notifications_read_at (read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS journaux_audit (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_user BIGINT UNSIGNED NULL,
    action VARCHAR(180) NOT NULL,
    module VARCHAR(100) NULL,
    auditable_type VARCHAR(150) NULL,
    auditable_id BIGINT UNSIGNED NULL,
    anciennes_valeurs JSON NULL,
    nouvelles_valeurs JSON NULL,
    adresse_ip VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_user_date (id_user, created_at),
    INDEX idx_audit_cible (auditable_type, auditable_id),
    CONSTRAINT fk_audit_user FOREIGN KEY (id_user) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
