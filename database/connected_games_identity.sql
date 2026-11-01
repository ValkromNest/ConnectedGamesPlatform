-- Nuova installazione: eseguire esclusivamente nel database connected_games_identity vuoto.
-- Schema finale ricavato da modelli e vincoli applicativi, senza migrazioni.
-- Nessun account, credenziale, dispositivo o partita di esempio.

CREATE TABLE `tbl_identity_login_attempt` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source_hash` char(64) NOT NULL,
  `window_started_at` datetime NOT NULL,
  `failed_attempts` smallint(6) NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_identity_login_attempt_source` (`source_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `tbl_identity_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subject_id` char(36) NOT NULL,
  `legacy_user_id` int(11) DEFAULT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` varchar(20) NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `failed_login_attempts` smallint(6) NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `keycloak_subject` varchar(255) DEFAULT NULL,
  `keycloak_issuer` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_identity_keycloak` (`keycloak_issuer`,`keycloak_subject`),
  UNIQUE KEY `uq_identity_user_subject` (`subject_id`),
  UNIQUE KEY `uq_identity_user_username` (`username`),
  UNIQUE KEY `uq_identity_user_legacy_id` (`legacy_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `tbl_identity_access_token` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `identity_user_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `created_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `revoked_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_identity_access_token_hash` (`token_hash`),
  KEY `ix_identity_access_user_expiry` (`identity_user_id`,`expires_at`),
  CONSTRAINT `fk_identity_access_user` FOREIGN KEY (`identity_user_id`) REFERENCES `tbl_identity_user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
