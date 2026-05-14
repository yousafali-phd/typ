-- ============================================================
-- Database Update Script - Typing Test System
-- Adds center_id to users table and updates structure
-- ============================================================

-- First, create centers table if it doesn't exist
CREATE TABLE IF NOT EXISTS `centers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `capacity` int DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Add a default center if none exists
INSERT IGNORE INTO `centers` (`id`, `name`, `code`, `is_active`) 
VALUES (1, 'Main Center', 'MC-01', 1);

SET @users_center_id_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'center_id');
SET @users_center_code_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'center_code');
SET @users_center_name_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'center_name');
SET @users_image_url_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'image_url');
SET @users_post_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'post');
SET @users_last_login_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'last_login');
SET @users_created_at_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'created_at');
SET @users_updated_at_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'updated_at');

SET @sql = IF(@users_center_id_exists = 0, 'ALTER TABLE `users` ADD COLUMN `center_id` int DEFAULT NULL AFTER `cnic`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(@users_post_exists = 0, 'ALTER TABLE `users` ADD COLUMN `post` varchar(100) DEFAULT NULL AFTER `name`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(@users_center_code_exists = 0, 'ALTER TABLE `users` ADD COLUMN `center_code` varchar(50) DEFAULT NULL AFTER `center_id`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(@users_center_name_exists = 0, 'ALTER TABLE `users` ADD COLUMN `center_name` varchar(255) DEFAULT NULL AFTER `center_code`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(@users_image_url_exists = 0, 'ALTER TABLE `users` ADD COLUMN `image_url` varchar(500) DEFAULT NULL AFTER `center_name`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Create type codes if they don't exist
CREATE TABLE IF NOT EXISTS `type_codes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_type_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

INSERT IGNORE INTO `type_codes` (`code`, `name`, `description`, `is_active`) VALUES
(1, 'Normal', 'Default candidate and paragraph type', 1),
(2, 'Disable', 'Special accommodation type', 1),
(3, 'Minority / Female', 'Special accommodation type', 1);

-- Add last_login for the live login flow
SET @sql = IF(@users_last_login_exists = 0, 'ALTER TABLE `users` ADD COLUMN `last_login` datetime DEFAULT NULL AFTER `allow_retest`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index for center_id
SET @users_idx_center_id_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_center_id');
SET @sql = IF(@users_idx_center_id_exists = 0, 'ALTER TABLE `users` ADD INDEX `idx_center_id` (`center_id`)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update existing users to have a default center (center_id = 1)
UPDATE `users` SET `center_id` = 1 WHERE `center_id` IS NULL OR `center_id` = 0;

-- Backfill center metadata from centers table
UPDATE `users` u
LEFT JOIN `centers` c ON c.id = u.center_id
SET
  u.center_code = CASE
    WHEN c.code IS NOT NULL AND c.code <> '' THEN c.code
    ELSE u.center_code
  END,
  u.center_name = CASE
    WHEN c.name IS NOT NULL AND c.name <> '' THEN c.name
    ELSE u.center_name
  END;

-- Optimize the users table structure
ALTER TABLE `users` 
MODIFY COLUMN `tested` tinyint(1) DEFAULT 0,
MODIFY COLUMN `allow_retest` tinyint(1) DEFAULT 0,
MODIFY COLUMN `logged_once` tinyint(1) DEFAULT 0,
MODIFY COLUMN `name` varchar(100) DEFAULT NULL,
MODIFY COLUMN `fathername` varchar(100) DEFAULT NULL,
MODIFY COLUMN `wpm_required` int DEFAULT NULL,
MODIFY COLUMN `project` varchar(50) NOT NULL DEFAULT '';

-- Add timestamps if they don't exist
SET @sql = IF(@users_created_at_exists = 0, 'ALTER TABLE `users` ADD COLUMN `created_at` timestamp DEFAULT CURRENT_TIMESTAMP', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(@users_updated_at_exists = 0, 'ALTER TABLE `users` ADD COLUMN `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Create lab_sessions table if it doesn't exist
CREATE TABLE IF NOT EXISTS `lab_sessions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `center_id` int NOT NULL,
  `session_password` varchar(6) NOT NULL,
  `status` enum('active','expired') DEFAULT 'active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_center_id` (`center_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Create live_candidates table if it doesn't exist
CREATE TABLE IF NOT EXISTS `live_candidates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `center_id` int NOT NULL,
  `started_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `finished_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_center_id` (`center_id`),
  KEY `idx_finished` (`finished_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Update results table to include center_id if it doesn't exist
SET @results_center_id_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'results' AND COLUMN_NAME = 'center_id');
SET @results_idx_center_id_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'results' AND INDEX_NAME = 'idx_center_id');

SET @sql = IF(@results_center_id_exists = 0, 'ALTER TABLE `results` ADD COLUMN `center_id` int DEFAULT NULL AFTER `uid`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index for center_id in results
SET @sql = IF(@results_idx_center_id_exists = 0, 'ALTER TABLE `results` ADD INDEX `idx_center_id` (`center_id`)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `paragraphs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `type_code` tinyint(1) NOT NULL DEFAULT 1,
  `type_name` varchar(100) NOT NULL DEFAULT 'Normal',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

SET @paragraph_type_code_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'paragraphs' AND COLUMN_NAME = 'type_code');
SET @paragraph_type_name_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'paragraphs' AND COLUMN_NAME = 'type_name');

SET @sql = IF(@paragraph_type_code_exists = 0, 'ALTER TABLE `paragraphs` ADD COLUMN `type_code` tinyint(1) NOT NULL DEFAULT 1', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(@paragraph_type_name_exists = 0, 'ALTER TABLE `paragraphs` ADD COLUMN `type_name` varchar(100) NOT NULL DEFAULT ''Normal''', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Insert a default paragraph if none exists
INSERT IGNORE INTO `paragraphs` (`id`, `title`, `content`, `is_active`) VALUES
(1, 'Sample Paragraph - General Knowledge', 'The quick brown fox jumps over the lazy dog. This sentence contains every letter of the English alphabet. Typing practice is essential for improving speed and accuracy in modern computing environments. Regular practice sessions help develop muscle memory and keyboard familiarity.', 1);

-- Global app settings
CREATE TABLE IF NOT EXISTS `app_settings` (
  `skey` varchar(100) NOT NULL,
  `svalue` varchar(255) NOT NULL,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`skey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

INSERT IGNORE INTO `app_settings` (`skey`, `svalue`) VALUES
  ('default_test_minutes', '1');

-- Center incharge accounts (assigned by admin)
CREATE TABLE IF NOT EXISTS `center_incharges` (
  `id` int NOT NULL AUTO_INCREMENT,
  `center_id` int DEFAULT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(200) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_incharge_username` (`username`),
  KEY `idx_center` (`center_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Retest approval logs by center incharge
CREATE TABLE IF NOT EXISTS `retest_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `center_id` int NOT NULL,
  `incharge_id` int NOT NULL,
  `reason` varchar(500) NOT NULL,
  `allowed_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_retest_user` (`user_id`),
  KEY `idx_retest_center` (`center_id`),
  KEY `idx_retest_incharge` (`incharge_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Candidate center change history logs
CREATE TABLE IF NOT EXISTS `candidate_center_change_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `old_center_id` int NOT NULL,
  `new_center_id` int NOT NULL,
  `changed_by_role` enum('admin','incharge') NOT NULL,
  `changed_by_id` int NOT NULL DEFAULT 0,
  `reason` varchar(500) NOT NULL,
  `changed_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ccl_user` (`user_id`),
  KEY `idx_ccl_old_center` (`old_center_id`),
  KEY `idx_ccl_new_center` (`new_center_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

COMMIT;
