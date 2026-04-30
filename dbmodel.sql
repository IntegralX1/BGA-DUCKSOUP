-- ------
-- BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
-- duckSoupTheRestaurantGame implementation
--
-- dbmodel.sql — Duck Soup complete database schema
-- ------

-- =============================================================
-- PLAYER TABLE EXTENSIONS
-- =============================================================

ALTER TABLE `player`
    ADD `player_duckats`        INT UNSIGNED NOT NULL DEFAULT 300,
    ADD `player_souper_duckats` INT UNSIGNED NOT NULL DEFAULT 3,
    ADD `player_board_position` INT UNSIGNED NOT NULL DEFAULT 0,
    ADD `player_restaurant_name` VARCHAR(64) NOT NULL DEFAULT '',
    ADD `player_on_vacation`    TINYINT(1)  NOT NULL DEFAULT 0;

-- =============================================================
-- QUESTIONS TABLE
-- Loaded once at game start from the CSV data via setupNewGame.
-- Each game instance gets its own copy so concurrent games are
-- fully isolated.
-- =============================================================

CREATE TABLE IF NOT EXISTS `question` (
    `question_id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `duckats_value`  TINYINT UNSIGNED NOT NULL COMMENT '30=T/F, 40=MC3, 50=MC4',
    `category`       VARCHAR(32)  NOT NULL,
    `question_text`  TEXT         NOT NULL,
    `answer_a`       VARCHAR(255) NOT NULL,
    `answer_b`       VARCHAR(255) NOT NULL,
    `answer_c`       VARCHAR(255) DEFAULT NULL COMMENT 'NULL for True/False questions',
    `answer_d`       VARCHAR(255) DEFAULT NULL COMMENT 'NULL for MC3 questions',
    `correct_answer` CHAR(1)      NOT NULL COMMENT 'A, B, C or D',
    `answer_text`    TEXT         DEFAULT NULL COMMENT 'Explanation shown after answer',
    `card_order`     INT UNSIGNED NOT NULL COMMENT 'Shuffled draw order',
    `used`           TINYINT(1)  NOT NULL DEFAULT 0 COMMENT '1 = already drawn this game',
    PRIMARY KEY (`question_id`),
    KEY `idx_draw_order` (`used`, `card_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================
-- EXCELLENT STAFF TABLE
-- One row per staff slot per player.
-- The 12 staff positions mirror the physical Staff Board.
-- =============================================================

CREATE TABLE IF NOT EXISTS `staff` (
    `staff_id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `player_id`      INT UNSIGNED NOT NULL,
    `staff_type`     VARCHAR(32)  NOT NULL COMMENT 'chef, sous_chef, first_cook, cook_1, cook_2, cook_3, maitre_d, sommelier, captain, server_1, server_2, server_3',
    `staff_location` VARCHAR(16)  NOT NULL DEFAULT 'kitchen' COMMENT 'kitchen or dining_room',
    `is_excellent`   TINYINT(1)  NOT NULL DEFAULT 0 COMMENT '0=average (no value), 1=excellent',
    `staff_value`    TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Duckat hire cost & bonus base',
    PRIMARY KEY (`staff_id`),
    KEY `idx_player_staff` (`player_id`, `staff_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================
-- STAFF BOX TABLE
-- Tracks which Excellent staff tiles are still available in the
-- central Staff Box (not yet hired by any player).
-- =============================================================

CREATE TABLE IF NOT EXISTS `staff_box` (
    `staff_type`     VARCHAR(32)  NOT NULL,
    `staff_location` VARCHAR(16)  NOT NULL,
    `staff_value`    TINYINT UNSIGNED NOT NULL,
    `available`      TINYINT(1)  NOT NULL DEFAULT 1,
    PRIMARY KEY (`staff_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================
-- RESTAURANT CARDS TABLE
-- Deck of restaurant event cards drawn on RESTAURANT squares.
-- =============================================================

CREATE TABLE IF NOT EXISTS `restaurant_card` (
    `card_id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `card_type`      VARCHAR(32)  NOT NULL COMMENT 'e.g. pay_bank, collect_bank, hire_free, lose_staff, etc.',
    `description`    TEXT         NOT NULL,
    `effect_value`   INT          NOT NULL DEFAULT 0 COMMENT 'Duckat amount or modifier, negative = pay',
    `card_order`     INT UNSIGNED NOT NULL COMMENT 'Shuffled draw order',
    PRIMARY KEY (`card_id`),
    KEY `idx_card_order` (`card_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================
-- AUCTION TABLE
-- Tracks an active Staff Quits / Help Wanted bidding round.
-- Cleared after each auction resolves.
-- =============================================================

CREATE TABLE IF NOT EXISTS `auction` (
    `auction_id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `staff_type`       VARCHAR(32)  NOT NULL COMMENT 'Which staff is up for bid',
    `staff_value`      TINYINT UNSIGNED NOT NULL COMMENT 'Base value of the staff tile',
    `source`           VARCHAR(16)  NOT NULL COMMENT 'staff_quits or help_wanted',
    `original_owner`   INT UNSIGNED DEFAULT NULL COMMENT 'player_id who lost the staff (staff_quits only)',
    `current_high_bidder` INT UNSIGNED DEFAULT NULL,
    `current_high_bid` INT UNSIGNED NOT NULL DEFAULT 0,
    `next_bidder`      INT UNSIGNED DEFAULT NULL COMMENT 'Whose turn it is to bid',
    `status`           VARCHAR(16)  NOT NULL DEFAULT 'active' COMMENT 'active, resolved, no_takers',
    PRIMARY KEY (`auction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================
-- GAME LOG / TURN TRACKER
-- Lightweight record of each turn for progression calculation
-- and zombie-turn safety.
-- =============================================================

CREATE TABLE IF NOT EXISTS `turn_log` (
    `log_id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `player_id`      INT UNSIGNED NOT NULL,
    `turn_number`    INT UNSIGNED NOT NULL,
    `phase`          VARCHAR(32)  NOT NULL COMMENT 'question, move, square_effect, auction, end_turn',
    `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`log_id`),
    KEY `idx_turn` (`turn_number`, `player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
