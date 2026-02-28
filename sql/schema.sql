-- ============================================================
-- Syringe Box QR Manager — Database Schema
-- Import with: mysql -u root -p your_database < schema.sql
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ------------------------------------------------------------
-- boxes: one row per physical 3D-printed syringe box
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `boxes` (
  `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `hash`       VARCHAR(64)     NOT NULL COMMENT '64-char hex from random_bytes(32)',
  `name`       VARCHAR(255)    NOT NULL,
  `rows`       TINYINT UNSIGNED NOT NULL DEFAULT 3,
  `cols`       TINYINT UNSIGNED NOT NULL DEFAULT 3,
  `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hash` (`hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- medicines: global list managed by admin
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `medicines` (
  `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(255)    NOT NULL,
  `color`      CHAR(7)         NOT NULL DEFAULT '#3b82f6' COMMENT 'Hex color for UI badge',
  `unit`       VARCHAR(20)     NOT NULL DEFAULT 'units' COMMENT 'e.g. mg, mL, units',
  `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- cells: one row per grid position per box
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cells` (
  `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `box_id`       INT UNSIGNED    NOT NULL,
  `row_idx`      TINYINT UNSIGNED NOT NULL,
  `col_idx`      TINYINT UNSIGNED NOT NULL,
  `medicine_id`  INT UNSIGNED    DEFAULT NULL,
  `quantity`     SMALLINT        NOT NULL DEFAULT 0,
  `max_quantity` SMALLINT        NOT NULL DEFAULT 10,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_box_cell` (`box_id`, `row_idx`, `col_idx`),
  CONSTRAINT `fk_cell_box`      FOREIGN KEY (`box_id`)      REFERENCES `boxes`(`id`)     ON DELETE CASCADE,
  CONSTRAINT `fk_cell_medicine` FOREIGN KEY (`medicine_id`) REFERENCES `medicines`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- access_logs: every public QR page load
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `access_logs` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `box_id`      INT UNSIGNED NOT NULL,
  `ip_address`  VARCHAR(45)  DEFAULT NULL,
  `user_agent`  TEXT         DEFAULT NULL,
  `accessed_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_box_id` (`box_id`),
  KEY `idx_accessed_at` (`accessed_at`),
  CONSTRAINT `fk_log_box` FOREIGN KEY (`box_id`) REFERENCES `boxes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- admins: login credentials for the admin section
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admins` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(255) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Default admin: admin / admin123
-- IMPORTANT: Change this password immediately after first login!
-- ------------------------------------------------------------
INSERT IGNORE INTO `admins` (`username`, `password_hash`)
VALUES ('admin', '$2y$12$YourHashHere');
-- Run this PHP to get the correct hash and update manually:
-- echo password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 12]);
-- Or use the setup helper: admin/setup.php

-- ------------------------------------------------------------
-- Sample medicines to get started
-- ------------------------------------------------------------
INSERT IGNORE INTO `medicines` (`id`, `name`, `color`, `unit`) VALUES
(1, 'Insulin',       '#3b82f6', 'units'),
(2, 'Saline',        '#10b981', 'mL'),
(3, 'Epinephrine',   '#ef4444', 'mg'),
(4, 'Morphine',      '#8b5cf6', 'mg'),
(5, 'Atropine',      '#f59e0b', 'mg');
