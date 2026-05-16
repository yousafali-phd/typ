-- Create archives table for archive management system
-- This table tracks all database backups/archives created by admins

CREATE TABLE IF NOT EXISTS `archives` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `archive_name` VARCHAR(255) NOT NULL UNIQUE,
  `db_name` VARCHAR(255) NOT NULL UNIQUE COMMENT 'Database name like typing_archive_Q1_2026',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED,
  `status` VARCHAR(50) DEFAULT 'active' COMMENT 'active, archived, deleted',
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_archive_name` (`archive_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Archive metadata table to track database backups and replicas';

-- Create index on created_at for sorting archives by date
CREATE INDEX IF NOT EXISTS `idx_archives_created` ON `archives` (`created_at` DESC);
