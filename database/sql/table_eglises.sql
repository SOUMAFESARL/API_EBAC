-- Table des églises EBAC
-- Compatible MySQL 8+ / MariaDB avec prise en charge du type JSON.
-- La table `users` doit exister avant l'exécution de ce script.

CREATE TABLE IF NOT EXISTS `eglises` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(30) NOT NULL,
    `nom` VARCHAR(180) NOT NULL,
    `denomination` VARCHAR(180) NULL,

    `adresse` VARCHAR(255) NULL,
    `region` VARCHAR(120) NULL,
    `district` VARCHAR(120) NULL,
    `ville_commune` VARCHAR(120) NOT NULL,

    `telephone` VARCHAR(30) NULL,
    `email` VARCHAR(150) NULL,
    `statut` ENUM('Active', 'Suspendue', 'Archivée') NOT NULL DEFAULT 'Active',
    `capacite_max_stagiaires` SMALLINT UNSIGNED NOT NULL DEFAULT 0,

    `representants` JSON NULL COMMENT 'Tableau JSON : nom_complet, fonction, telephone, email',
    `observations` TEXT NULL,

    `user_id` BIGINT UNSIGNED NULL,
    `user_code` VARCHAR(150) NULL COMMENT '',
    `created_by` BIGINT UNSIGNED NULL,
    `updated_by` BIGINT UNSIGNED NULL,
    `deleted_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_eglises_code` (`code`),
    KEY `idx_eglises_nom` (`nom`),
    KEY `idx_eglises_statut_ville` (`statut`, `ville_commune`),
    KEY `idx_eglises_user_id` (`user_id`),
    KEY `idx_eglises_user_code` (`user_code`),
    KEY `idx_eglises_created_by` (`created_by`),
    KEY `idx_eglises_updated_by` (`updated_by`),
    KEY `idx_eglises_deleted_by` (`deleted_by`),

    CONSTRAINT `fk_eglises_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_eglises_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_eglises_updated_by`
        FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_eglises_deleted_by`
        FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Exemple de création d'une église avec plusieurs représentants.
-- Le code doit normalement être généré automatiquement par l'API.
/*
INSERT INTO `eglises` (
    `code`,
    `nom`,
    `denomination`,
    `adresse`,
    `region`,
    `district`,
    `ville_commune`,
    `telephone`,
    `email`,
    `statut`,
    `capacite_max_stagiaires`,
    `representants`,
    `observations`,
    `created_at`,
    `updated_at`
) VALUES (
    'EGL-000001',
    'Église Exemple',
    'Alliance chrétienne',
    'Rue des Jardins, II Plateaux',
    'Abidjan',
    'District de Dokui',
    'Abobo',
    '+225 27 22 44 55 66',
    'contact@eglise.ci',
    'Active',
    1,
    JSON_ARRAY(
        JSON_OBJECT(
            'nom_complet', 'Past. YAO Thomas',
            'fonction', 'Représentant principal',
            'telephone', '+225 07 07 44 55 66',
            'email', 't.yao@partenaire.ci'
        ),
        JSON_OBJECT(
            'nom_complet', 'KOFFI Jean',
            'fonction', 'Encadreur de stage',
            'telephone', '+225 05 00 00 00 00',
            'email', 'koffi@example.ci'
        )
    ),
    'Convention d’accueil à vérifier.',
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
);
*/
