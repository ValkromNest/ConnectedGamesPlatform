-- Nuova installazione: eseguire esclusivamente nel database connected_games_dartsgame vuoto.
-- Schema finale ricavato da modelli e vincoli applicativi, senza migrazioni.
-- Nessun account, credenziale, dispositivo o partita di esempio.

CREATE TABLE `darts_game_match` (
  `match_id` varchar(64) NOT NULL,
  `status` varchar(16) NOT NULL,
  `starting_score` smallint(6) NOT NULL DEFAULT 501,
  `context_hash` char(64) NOT NULL,
  `context_request_key` char(36) NOT NULL,
  `created_at` datetime NOT NULL,
  `finished_at` datetime DEFAULT NULL,
  PRIMARY KEY (`match_id`),
  UNIQUE KEY `uq_darts_game_match_context_key` (`context_request_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `darts_game_player` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `match_id` varchar(64) NOT NULL,
  `player_id` varchar(64) NOT NULL,
  `display_name` varchar(120) NOT NULL,
  `turn_order` smallint(6) NOT NULL,
  `remaining_score` smallint(6) NOT NULL DEFAULT 501,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_darts_game_player_match_player` (`match_id`,`player_id`),
  UNIQUE KEY `uq_darts_game_player_match_order` (`match_id`,`turn_order`),
  CONSTRAINT `fk_darts_game_player_match` FOREIGN KEY (`match_id`) REFERENCES `darts_game_match` (`match_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `darts_game_throw` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `match_id` varchar(64) NOT NULL,
  `player_id` varchar(64) NOT NULL,
  `device_id` varchar(64) NOT NULL,
  `sector` tinyint(3) NOT NULL,
  `multiplier` tinyint(3) NOT NULL,
  `score` smallint(6) NOT NULL,
  `result` varchar(16) NOT NULL,
  `idempotency_key` char(36) NOT NULL,
  `request_hash` char(64) NOT NULL,
  `response_json` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_darts_game_throw_match_idempotency` (`match_id`,`idempotency_key`),
  KEY `ix_darts_game_throw_match_id` (`match_id`,`id`),
  KEY `fk_darts_game_throw_player` (`match_id`,`player_id`),
  CONSTRAINT `fk_darts_game_throw_player` FOREIGN KEY (`match_id`, `player_id`) REFERENCES `darts_game_player` (`match_id`, `player_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
