-- Nuova installazione: eseguire esclusivamente nel database connected_games_db vuoto.
-- Schema finale ricavato da modelli e vincoli applicativi, senza migrazioni.
-- Nessun account, credenziale, dispositivo o partita di esempio.

CREATE TABLE `sys_autoconfigurazione` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  `valore` longtext DEFAULT NULL,
  `descrizione` longtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_sys_autoconfigurazione_nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `tbl_game` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(50) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `tbl_device` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `game_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `token_encrypted` text DEFAULT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_tbl_device_name` (`name`),
  UNIQUE KEY `ux_tbl_device_token_hash` (`token_hash`),
  KEY `ix_tbl_device_game_id` (`game_id`),
  CONSTRAINT `fk_tbl_device_game_id` FOREIGN KEY (`game_id`) REFERENCES `tbl_game` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `tbl_matches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `game_id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `max_players` int(11) NOT NULL DEFAULT 2,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `started_at` datetime DEFAULT NULL,
  `ended_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `game_id` (`game_id`),
  CONSTRAINT `tbl_matches_ibfk_1` FOREIGN KEY (`game_id`) REFERENCES `tbl_game` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `tbl_match_players` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `match_id` int(11) NOT NULL,
  `identity_subject_id` varchar(64) NOT NULL,
  `display_name` varchar(120) NOT NULL,
  `score` int(11) NOT NULL DEFAULT 0,
  `turn_order` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tbl_match_players_match_subject` (`match_id`,`identity_subject_id`),
  KEY `ix_tbl_match_players_match_id` (`match_id`),
  CONSTRAINT `tbl_match_players_ibfk_1` FOREIGN KEY (`match_id`) REFERENCES `tbl_matches` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `tbl_game` (`name`, `code`, `enabled`) VALUES ('Freccette', 'darts', 1);
